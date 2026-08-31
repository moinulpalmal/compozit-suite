<?php

namespace App\Services\Merchandising\PoParser\TextExtractor;

use App\Exceptions\Merchandising\PoParser\TextExtractionException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Reads a PDF as fixed-width text through Xpdf's `pdftotext -layout`.
 *
 * `-layout` is what preserves the column positions the extractors slice on, so it
 * is not optional. **The build must be Xpdf, not Poppler** — both answer to
 * `pdftotext` and both accept `-layout`, but their spacing is not byte-identical.
 * `config/po-parser.php` says how to tell them apart.
 *
 * A PDF that yields almost no text is a scan. That is reported as its own message
 * rather than being allowed through as a document with no purchase orders in it,
 * because the two have completely different remedies.
 */
final class PdfTextExtractor
{
    /** Below this many non-empty lines, the PDF is treated as a scan. */
    private const int MINIMUM_TEXT_LINES = 5;

    /**
     * @return list<string>
     *
     * @throws TextExtractionException
     */
    public function extract(string $pdfPath): array
    {
        if (! is_file($pdfPath)) {
            throw new TextExtractionException('PDF file not found: '.$pdfPath);
        }

        $binary = $this->resolveBinary();
        $outFile = $this->makeTempFile();

        // The scratch file goes on every exit path, failures and timeouts included.
        try {
            $this->run($binary, $pdfPath, $outFile);
            $content = $this->readOutput($outFile);
        } finally {
            if (is_file($outFile)) {
                @unlink($outFile);
            }
        }

        return $this->toLines($content);
    }

    /**
     * @throws TextExtractionException
     */
    private function resolveBinary(): string
    {
        $binary = (string) config('po-parser.pdftotext.bin');

        if (trim($binary) === '') {
            throw new TextExtractionException(
                'pdftotext is not configured. Set PDFTOTEXT_BIN in .env to the full path of the '
                .'Xpdf pdftotext executable. Only .pdf uploads need it.'
            );
        }

        // A bare command name is resolved through PATH by Process, so only an
        // explicit path can be checked here.
        if (strpbrk($binary, '/\\') !== false && ! is_file($binary)) {
            throw new TextExtractionException('pdftotext not found at: '.$binary.'. Check PDFTOTEXT_BIN in .env.');
        }

        return $binary;
    }

    /**
     * @throws TextExtractionException
     */
    private function makeTempFile(): string
    {
        $tmpDir = storage_path('app/'.config('po-parser.storage.tmp'));

        // The is_dir() re-check keeps this correct against a concurrent mkdir.
        if (! is_dir($tmpDir) && ! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new TextExtractionException('Unable to create temp directory: '.$tmpDir);
        }

        if (! is_writable($tmpDir)) {
            throw new TextExtractionException('Temp directory is not writable: '.$tmpDir);
        }

        return $tmpDir.DIRECTORY_SEPARATOR.'pdf_'.bin2hex(random_bytes(8)).'.txt';
    }

    /**
     * @throws TextExtractionException
     */
    private function run(string $binary, string $pdfPath, string $outFile): void
    {
        // Array form, so spaces in Windows paths need no shell quoting.
        $command = [$binary, '-layout'];

        $encoding = config('po-parser.pdftotext.encoding');

        if ($encoding) {
            $command[] = '-enc';
            $command[] = $encoding;
        }

        $command[] = $pdfPath;
        $command[] = $outFile;

        $timeout = (float) config('po-parser.pdftotext.timeout');

        $process = new Process($command);
        $process->setTimeout($timeout > 0 ? $timeout : null);

        try {
            $process->run();
        } catch (ProcessRuntimeException $exception) {
            // Launch failures and timeouts, which would otherwise surface as a 500.
            throw new TextExtractionException(
                'pdftotext could not be executed ('.$binary.'): '.$exception->getMessage()
            );
        }

        if (! $process->isSuccessful()) {
            throw new TextExtractionException(
                'pdftotext failed (exit code '.$process->getExitCode().'): '
                .trim($process->getErrorOutput().' '.$process->getOutput())
            );
        }
    }

    /**
     * @throws TextExtractionException
     */
    private function readOutput(string $outFile): string
    {
        if (! is_file($outFile)) {
            throw new TextExtractionException('pdftotext produced no output.');
        }

        $content = file_get_contents($outFile);

        if ($content === false) {
            throw new TextExtractionException('Unable to read pdftotext output.');
        }

        // Some builds prefix UTF-8 output with a BOM, which would otherwise corrupt
        // the first line and break page-header matching.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (trim($content) === '') {
            throw new TextExtractionException(
                'The PDF contains no extractable text. It is most likely a scan, which needs OCR before it can be parsed.'
            );
        }

        return $content;
    }

    /**
     * @return list<string>
     *
     * @throws TextExtractionException
     */
    private function toLines(string $content): array
    {
        // pdftotext marks each page break with a form feed; a blank line is what
        // the rest of the pipeline expects.
        $content = str_replace("\x0C", "\n", $content);

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];

        $nonEmpty = count(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        if ($nonEmpty < self::MINIMUM_TEXT_LINES) {
            throw new TextExtractionException(
                'The PDF yielded almost no text. It is most likely a scan, which needs OCR before it can be parsed.'
            );
        }

        return $lines;
    }
}
