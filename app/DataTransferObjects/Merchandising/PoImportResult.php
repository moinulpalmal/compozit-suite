<?php

namespace App\DataTransferObjects\Merchandising;

use App\Models\Merchandising\PoImport;

/**
 * What one import actually did, so the controller can report it accurately.
 *
 * A single document holds several purchase orders and they need not all land: some
 * may be revisions of orders already held, and some may be byte-identical re-uploads
 * that are refused. Returning only the {@see PoImport} would leave the controller
 * guessing which message to show.
 */
final readonly class PoImportResult
{
    /**
     * @param  list<string>  $importedPoNumbers  orders newly stored
     * @param  list<string>  $revisedPoNumbers  orders stored as a new revision of one already held
     * @param  list<string>  $duplicatePoNumbers  orders refused as an identical re-upload
     */
    public function __construct(
        public PoImport $import,
        public array $importedPoNumbers,
        public array $revisedPoNumbers,
        public array $duplicatePoNumbers,
    ) {}

    /**
     * How many orders were written, revisions included.
     */
    public function storedCount(): int
    {
        return count($this->importedPoNumbers) + count($this->revisedPoNumbers);
    }

    /**
     * Whether anything in the document was refused as already held.
     */
    public function hasDuplicates(): bool
    {
        return $this->duplicatePoNumbers !== [];
    }

    /**
     * Whether the document produced nothing at all — every order was a duplicate.
     */
    public function storedNothing(): bool
    {
        return $this->storedCount() === 0;
    }
}
