<?php

namespace App\Services\Merchandising\PoParser\TextExtractor;

use App\Exceptions\Merchandising\PoParser\TextExtractionException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Converts a legacy `.doc` or `.rtf` to `.docx` with LibreOffice, so
 * {@see DocxTextExtractor} can read it.
 *
 * Every flag here earns its place, and the environment block is not decoration:
 * `soffice.bin` on Windows crashes with `0xC0000409` when spawned from a persistent
 * PHP server — which `php artisan serve`, and therefore `composer run dev`, is.
 * Forcing the headless `svp` VCL plugin and pointing every home/temp variable at a
 * throwaway profile avoids it. A shared profile also lets one conversion lock out
 * the next, so the profile is per-invocation.
 *
 * Requires LibreOffice on the machine — see `documentation/deployment.md`.
 */
final class DocNormalizer
{
    /**
     * @return string the path of the produced `.docx`
     *
     * @throws TextExtractionException
     */
    public function toDocx(string $sourcePath, string $tmpDir): string
    {
        $binary = $this->resolveBinary();

        $this->ensureDirectory($tmpDir);

        $profileDir = $tmpDir.DIRECTORY_SEPARATOR.'lo_profile_'.bin2hex(random_bytes(8));
        $this->ensureDirectory($profileDir);

        try {
            $this->run($binary, $sourcePath, $tmpDir, $profileDir);
        } finally {
            $this->deleteDirectory($profileDir);
        }

        $converted = $tmpDir.DIRECTORY_SEPARATOR.pathinfo($sourcePath, PATHINFO_FILENAME).'.docx';

        if (! is_file($converted)) {
            throw new TextExtractionException(
                'LibreOffice reported success but produced no .docx. Verify the installation at '.$binary.'.'
            );
        }

        return $converted;
    }

    /**
     * @throws TextExtractionException
     */
    private function resolveBinary(): string
    {
        $binary = (string) config('po-parser.libreoffice.bin');

        if (trim($binary) === '') {
            throw new TextExtractionException(
                'LibreOffice is not configured. Set LIBREOFFICE_BIN in .env to the full path of '
                .'soffice.exe (Windows) or soffice (Linux). Only .doc and .rtf uploads need it.'
            );
        }

        if (! is_file($binary)) {
            throw new TextExtractionException('LibreOffice not found at: '.$binary.'. Check LIBREOFFICE_BIN in .env.');
        }

        return $binary;
    }

    /**
     * @throws TextExtractionException
     */
    private function run(string $binary, string $sourcePath, string $outDir, string $profileDir): void
    {
        // Array form, so spaces in Windows paths need no shell quoting.
        $process = new Process([
            $binary,
            '--headless',
            '--norestore',
            '--nolockcheck',
            '--nologo',
            '--nofirststartwizard',
            '-env:UserInstallation='.$this->toFileUri($profileDir),
            '--convert-to', 'docx',
            '--outdir', $outDir,
            $sourcePath,
        ]);

        $process->setTimeout((float) config('po-parser.libreoffice.timeout'));
        $process->setEnv([
            'SAL_USE_VCLPLUGIN' => 'svp',
            'SAL_DISABLE_OPENGL' => 'true',
            'HOME' => $profileDir,
            'USERPROFILE' => $profileDir,
            'TEMP' => $profileDir,
            'TMP' => $profileDir,
        ]);

        try {
            $process->run();
        } catch (ProcessRuntimeException $exception) {
            // Launch failures and timeouts, which would otherwise surface as a 500.
            throw new TextExtractionException(
                'LibreOffice could not be executed ('.$binary.'): '.$exception->getMessage()
            );
        }

        if (! $process->isSuccessful()) {
            throw new TextExtractionException(
                'LibreOffice conversion failed (exit code '.$process->getExitCode().'): '
                .trim($process->getErrorOutput().' '.$process->getOutput())
            );
        }
    }

    /**
     * @throws TextExtractionException
     */
    private function ensureDirectory(string $path): void
    {
        // The is_dir() re-check keeps this correct against a concurrent mkdir.
        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new TextExtractionException('Unable to create directory: '.$path);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path.DIRECTORY_SEPARATOR.$entry;

            is_dir($child) ? $this->deleteDirectory($child) : @unlink($child);
        }

        @rmdir($path);
    }

    /**
     * LibreOffice wants `-env:UserInstallation` as a file URI, and the number of
     * leading slashes differs between a Windows drive letter and a Unix root.
     */
    private function toFileUri(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, '/') ? 'file://'.$path : 'file:///'.$path;
    }
}
