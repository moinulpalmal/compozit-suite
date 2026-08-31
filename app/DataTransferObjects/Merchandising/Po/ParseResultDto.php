<?php

namespace App\DataTransferObjects\Merchandising\Po;

use App\Enums\Merchandising\PoParseStatus;

/**
 * The outcome of parsing one uploaded file: every purchase order it held, plus
 * what the parser thinks of the job it did.
 *
 * This whole object is stored on the `po_imports` row, which is what keeps a
 * failed parse's warnings inspectable beside the document that produced them.
 */
final readonly class ParseResultDto
{
    /**
     * @param  list<PurchaseOrderDto>  $purchaseOrders
     * @param  list<WarningDto>  $globalWarnings
     */
    public function __construct(
        public string $sourceFileName,
        public string $detectedFileType,
        public string $templateFingerprint,
        public int $pageCount,
        public int $poCount,
        public float $overallConfidence,
        public PoParseStatus $status,
        public array $purchaseOrders,
        public array $globalWarnings,
        public string $parsedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_file_name' => $this->sourceFileName,
            'detected_file_type' => $this->detectedFileType,
            'template_fingerprint' => $this->templateFingerprint,
            'page_count' => $this->pageCount,
            'po_count' => $this->poCount,
            'overall_confidence' => $this->overallConfidence,
            'status' => $this->status->value,
            'parsed_at' => $this->parsedAt,
            'purchase_orders' => array_map(
                static fn (PurchaseOrderDto $po): array => $po->toArray(),
                $this->purchaseOrders,
            ),
            'global_warnings' => array_map(
                static fn (WarningDto $warning): array => $warning->toArray(),
                $this->globalWarnings,
            ),
        ];
    }
}
