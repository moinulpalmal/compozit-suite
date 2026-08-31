<?php

namespace App\Services\Merchandising\PoParser;

use App\DataTransferObjects\Merchandising\Po\ParseResultDto;
use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\DataTransferObjects\Merchandising\Po\WarningDto;
use App\Enums\Merchandising\PoFileType;
use App\Exceptions\Merchandising\PoParser\PoParserException;
use App\Exceptions\Merchandising\PoParser\PoValidationException;
use App\Services\Merchandising\PoParser\LineProcessor\LineNormalizer;
use App\Services\Merchandising\PoParser\LineProcessor\PageSplitter;
use App\Services\Merchandising\PoParser\StateMachine\SectionStateMachine;
use App\Services\Merchandising\PoParser\Support\Page;
use App\Services\Merchandising\PoParser\Support\ParseGrader;
use App\Services\Merchandising\PoParser\Support\TemplateFingerprint;
use App\Services\Merchandising\PoParser\TextExtractor\DocNormalizer;
use App\Services\Merchandising\PoParser\TextExtractor\DocxTextExtractor;
use App\Services\Merchandising\PoParser\TextExtractor\FileTypeDetector;
use App\Services\Merchandising\PoParser\TextExtractor\PdfTextExtractor;
use App\Services\Merchandising\PurchaseOrderImportService;

/**
 * Turns a Walmart purchase-order document into structured data.
 *
 * The pipeline, in order: detect the format from its magic bytes, extract
 * space-preserved lines through whichever toolchain that format needs, normalise and
 * number them, cut them into pages at the banner each page opens with, group the
 * pages by PO number, and build one purchase order per group.
 *
 * **This parser is specific to Walmart's import purchase-order template**, despite
 * the general class names. It recognises pages by a header of the form
 * `Purchase Order: <10 digits> … Page: <n>` and it will find nothing in any other
 * document. A second buyer's template means a second parser, not a wider regex.
 *
 * It takes a path rather than an upload so that it can be pointed at a fixture as
 * easily as at a stored file; storing the upload is
 * {@see PurchaseOrderImportService}'s job.
 */
final class ParserService
{
    public function __construct(
        private readonly FileTypeDetector $detector,
        private readonly DocNormalizer $docNormalizer,
        private readonly DocxTextExtractor $docxExtractor,
        private readonly PdfTextExtractor $pdfExtractor,
        private readonly LineNormalizer $lineNormalizer,
        private readonly PageSplitter $pageSplitter,
        private readonly SectionStateMachine $stateMachine,
        private readonly PurchaseOrderBuilder $builder,
        private readonly ParseGrader $grader,
    ) {}

    /**
     * @param  string  $absolutePath  the file on disk
     * @param  string  $sourceFileName  the name to report it by
     *
     * @throws PoParserException
     */
    public function parse(string $absolutePath, string $sourceFileName): ParseResultDto
    {
        $type = $this->detector->detect($absolutePath);

        $lines = $this->lineNormalizer->normalize($this->extractLines($type, $absolutePath));
        $pages = $this->pageSplitter->split($lines);

        $this->guardPageCount($pages);

        $poGroups = $this->groupPagesByPo($pages);

        $this->guardPoCount($poGroups);

        $purchaseOrders = [];
        $firstSegments = [];

        foreach ($poGroups as $poPages) {
            $segments = $this->stateMachine->run($this->flatten($poPages));

            if ($firstSegments === []) {
                $firstSegments = $segments;
            }

            $purchaseOrders[] = $this->builder->build($segments, count($poPages));
        }

        $warnings = $this->collectWarnings($purchaseOrders);

        return new ParseResultDto(
            sourceFileName: $sourceFileName,
            detectedFileType: $type->value,
            templateFingerprint: TemplateFingerprint::compute($firstSegments),
            pageCount: count($pages),
            poCount: count($purchaseOrders),
            overallConfidence: $this->grader->confidence($warnings),
            status: $this->grader->status($warnings),
            purchaseOrders: $purchaseOrders,
            globalWarnings: $warnings,
            parsedAt: now()->toIso8601String(),
        );
    }

    /**
     * Route the file through the toolchain its format needs.
     *
     * The intermediate `.docx` a legacy format produces is deleted straight after it
     * is read — leaving them accumulates a copy of every document ever imported in
     * the temp directory.
     *
     * @return list<string>
     *
     * @throws PoParserException
     */
    private function extractLines(PoFileType $type, string $absolutePath): array
    {
        if ($type === PoFileType::Pdf) {
            return $this->pdfExtractor->extract($absolutePath);
        }

        if (! $type->requiresLibreOffice()) {
            return $this->docxExtractor->extract($absolutePath);
        }

        $converted = $this->docNormalizer->toDocx($absolutePath, $this->tmpDir());

        try {
            return $this->docxExtractor->extract($converted);
        } finally {
            if (is_file($converted)) {
                @unlink($converted);
            }
        }
    }

    /**
     * @param  list<Page>  $pages
     *
     * @throws PoValidationException
     */
    private function guardPageCount(array $pages): void
    {
        $max = (int) config('po-parser.limits.max_pages');

        if ($max > 0 && count($pages) > $max) {
            throw new PoValidationException(
                'The document has '.count($pages).' pages, which exceeds the maximum of '.$max.'.'
            );
        }
    }

    /**
     * @param  array<string, list<Page>>  $poGroups
     *
     * @throws PoValidationException
     */
    private function guardPoCount(array $poGroups): void
    {
        if ($poGroups === []) {
            throw new PoValidationException(
                'No Walmart purchase-order pages were found in this document. Pages are recognised by a '
                .'header of the form "Purchase Order: <10 digits> ... Page: <n>".'
            );
        }

        $max = (int) config('po-parser.limits.max_pos_per_file');

        if ($max > 0 && count($poGroups) > $max) {
            throw new PoValidationException(
                'The document contains '.count($poGroups).' purchase orders, which exceeds the maximum of '.$max.'.'
            );
        }
    }

    /**
     * @param  list<Page>  $pages
     * @return array<string, list<Page>>
     */
    private function groupPagesByPo(array $pages): array
    {
        $groups = [];

        foreach ($pages as $page) {
            $groups[$page->poNumber][] = $page;
        }

        return $groups;
    }

    /**
     * @param  list<Page>  $pages
     * @return list<array{index: int, text: string}>
     */
    private function flatten(array $pages): array
    {
        $lines = [];

        foreach ($pages as $page) {
            foreach ($page->lines as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Every purchase order's warnings, flattened into one list for the file.
     *
     * @param  list<PurchaseOrderDto>  $purchaseOrders
     * @return list<WarningDto>
     */
    private function collectWarnings(array $purchaseOrders): array
    {
        $warnings = [];

        foreach ($purchaseOrders as $po) {
            foreach ($po->warnings as $warning) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    private function tmpDir(): string
    {
        return storage_path('app/'.config('po-parser.storage.tmp'));
    }
}
