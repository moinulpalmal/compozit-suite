<?php

namespace App\DataTransferObjects\Merchandising\Po;

use App\Enums\Merchandising\PoWarningSeverity;

/**
 * One thing the parser noticed and wants a human to know about.
 *
 * Warnings are the parser's only channel for "this looks wrong" — extraction
 * itself never throws for a missing field, because a Walmart template that has
 * drifted should produce a flagged import rather than a failed one.
 */
final readonly class WarningDto
{
    public function __construct(
        public string $code,
        public PoWarningSeverity $severity,
        public string $field,
        public ?string $poNumber,
        public ?int $page,
        public ?int $lineIndex,
        public string $message,
    ) {}

    /**
     * @return array{code: string, severity: string, field: string, po_number: string|null, page: int|null, line_index: int|null, message: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'field' => $this->field,
            'po_number' => $this->poNumber,
            'page' => $this->page,
            'line_index' => $this->lineIndex,
            'message' => $this->message,
        ];
    }
}
