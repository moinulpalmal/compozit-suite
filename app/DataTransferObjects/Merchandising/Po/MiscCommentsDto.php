<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The free-text `Misc comments` block, and the few structured values inside it.
 *
 * The block is absent from most orders. The extractor returns null rather than an
 * empty instance in that case, so "no misc comments" and "misc comments we could
 * not read" stay distinguishable.
 */
final readonly class MiscCommentsDto
{
    public function __construct(
        public ?string $raw = null,
        public ?string $ceCaseNumber = null,
        public ?string $discrepancyType = null,
        public ?string $updatedBy = null,
    ) {}

    /**
     * @return array{raw: string|null, ce_case_number: string|null, discrepancy_type: string|null, updated_by: string|null}
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'ce_case_number' => $this->ceCaseNumber,
            'discrepancy_type' => $this->discrepancyType,
            'updated_by' => $this->updatedBy,
        ];
    }
}
