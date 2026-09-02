<?php

namespace App\DataTransferObjects\Merchandising;

use App\Enums\Merchandising\BqsConflictDecision;
use App\Models\Merchandising\BqsSheet;

/**
 * What the uploader's decision did to a staged BQS.
 *
 * A revision is something a person **confirms**, never something an upload decides —
 * the same rule the purchase-order import arrived at. This carries the outcome back so
 * the controller can name it: which decision was taken, and what it produced.
 */
final readonly class BqsResolveResult
{
    public function __construct(
        public BqsConflictDecision $decision,
        public ?BqsSheet $sheet,
        public ?string $title = null,
    ) {}

    /**
     * Whether a BQS revision now exists as a result of the decision.
     */
    public function wasWritten(): bool
    {
        return $this->sheet instanceof BqsSheet;
    }

    /**
     * The revision number written, for the success message.
     */
    public function revisionNo(): ?int
    {
        return $this->sheet?->revision_no;
    }
}
