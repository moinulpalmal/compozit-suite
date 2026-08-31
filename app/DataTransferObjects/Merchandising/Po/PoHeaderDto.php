<?php

namespace App\DataTransferObjects\Merchandising\Po;

use App\Services\Merchandising\PoParser\Support\DateParser;

/**
 * The banner printed at the top of every page of a purchase order.
 *
 * `$revisedDate` and `$revisedBy` matter beyond display: they are Walmart's own
 * statement of which version of the order this document is, and the import path
 * keys revisions on them rather than inventing a counter of its own. See
 * [`documentation/merchandising.md`](../../../../documentation/merchandising.md).
 *
 * Dates are ISO-8601 strings, not `Carbon` — {@see DateParser}
 * explains why.
 */
final readonly class PoHeaderDto
{
    public function __construct(
        public ?string $poNumber = null,
        public ?string $status = null,
        public ?string $quoteId = null,
        public ?string $documentType = null,
        public ?string $createDate = null,
        public ?string $negotiationDate = null,
        public ?float $exchangeRate = null,
        public ?string $bidCurrency = null,
        public ?string $revisedDate = null,
        public ?string $revisedBy = null,
        public ?string $preclassStatus = null,
        public ?string $preclassApprovalDate = null,
        public ?string $preclassApprovalBy = null,
        public ?string $printDate = null,
        public ?string $printedBy = null,
        public int $pageCount = 0,
    ) {}

    /**
     * @return array<string, string|float|int|null>
     */
    public function toArray(): array
    {
        return [
            'po_number' => $this->poNumber,
            'status' => $this->status,
            'quote_id' => $this->quoteId,
            'document_type' => $this->documentType,
            'create_date' => $this->createDate,
            'negotiation_date' => $this->negotiationDate,
            'exchange_rate' => $this->exchangeRate,
            'bid_currency' => $this->bidCurrency,
            'revised_date' => $this->revisedDate,
            'revised_by' => $this->revisedBy,
            'preclass_status' => $this->preclassStatus,
            'preclass_approval_date' => $this->preclassApprovalDate,
            'preclass_approval_by' => $this->preclassApprovalBy,
            'print_date' => $this->printDate,
            'printed_by' => $this->printedBy,
            'page_count' => $this->pageCount,
        ];
    }
}
