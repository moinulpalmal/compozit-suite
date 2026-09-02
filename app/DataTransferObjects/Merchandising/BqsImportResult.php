<?php

namespace App\DataTransferObjects\Merchandising;

use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsSheet;
use App\Services\Merchandising\BqsImportService;

/**
 * What one BQS upload actually did, so the controller can report it accurately.
 *
 * Simpler than {@see PoImportResult}, and the difference is the point: a document can
 * hold fifty purchase orders that each land differently, whereas a workbook is **one**
 * BQS. So an upload has exactly three outcomes — it was stored, it was refused as a
 * byte-identical re-upload, or it collided and is waiting on a decision
 * ({@see BqsImportService::resolve()}).
 */
final readonly class BqsImportResult
{
    public function __construct(
        public BqsImport $import,
        public ?BqsSheet $sheet,
        public bool $isDuplicate,
        public bool $isStaged,
        public ?string $collidesWith = null,
    ) {}

    /**
     * The upload was written as a new BQS or a new revision.
     */
    public function wasStored(): bool
    {
        return $this->sheet instanceof BqsSheet;
    }

    /**
     * The upload is waiting on the uploader before it is finished.
     */
    public function needsDecision(): bool
    {
        return $this->isStaged;
    }

    /**
     * The workbook produced nothing and asks nothing — it was already held, byte for
     * byte, so there is nothing to decide about.
     */
    public function storedNothing(): bool
    {
        return ! $this->wasStored() && ! $this->needsDecision();
    }

    /**
     * How many rows were written, for the success message.
     */
    public function rowCount(): int
    {
        return $this->sheet?->row_count ?? 0;
    }
}
