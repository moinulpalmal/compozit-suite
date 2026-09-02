<?php

namespace App\DataTransferObjects\Merchandising;

use App\Models\Merchandising\PoImport;
use App\Services\Merchandising\PurchaseOrderImportService;

/**
 * What one upload actually did, so the controller can report it accurately.
 *
 * A single document holds several purchase orders and they need not all land the same
 * way: some are new, some are byte-identical re-uploads that are refused, and some
 * collide with an order already held and are **staged** for the uploader to decide
 * about ({@see PurchaseOrderImportService::resolve()}). Returning only the
 * {@see PoImport} would leave the controller guessing which message to show.
 *
 * There is no `revisedPoNumbers` here any more. A revision is now something a person
 * confirms rather than something the upload decides, so it belongs to
 * {@see PoResolveResult}.
 */
final readonly class PoImportResult
{
    /**
     * @param  list<string>  $importedPoNumbers  orders newly stored, colliding with nothing
     * @param  list<string>  $duplicatePoNumbers  orders refused as an identical re-upload
     * @param  list<string>  $stagedPoNumbers  orders held back, waiting on a decision
     */
    public function __construct(
        public PoImport $import,
        public array $importedPoNumbers,
        public array $duplicatePoNumbers,
        public array $stagedPoNumbers,
    ) {}

    /**
     * How many orders were written outright.
     */
    public function storedCount(): int
    {
        return count($this->importedPoNumbers);
    }

    /**
     * Whether anything in the document was refused as already held.
     */
    public function hasDuplicates(): bool
    {
        return $this->duplicatePoNumbers !== [];
    }

    /**
     * Whether the upload is waiting on the uploader before it is finished.
     */
    public function needsDecisions(): bool
    {
        return $this->stagedPoNumbers !== [];
    }

    /**
     * Whether the document produced nothing and asks nothing — every order was already held.
     */
    public function storedNothing(): bool
    {
        return $this->storedCount() === 0 && ! $this->needsDecisions();
    }
}
