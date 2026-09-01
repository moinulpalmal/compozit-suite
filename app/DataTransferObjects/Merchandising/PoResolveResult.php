<?php

namespace App\DataTransferObjects\Merchandising;

use App\Models\Merchandising\PoImport;

/**
 * What the uploader decided about the orders an import held back.
 *
 * The three lists are mutually exclusive and together account for every staged order,
 * which is what lets the controller report the outcome without recounting rows.
 */
final readonly class PoResolveResult
{
    /**
     * @param  list<string>  $revisedPoNumbers  stored as the next revision, the held order kept
     * @param  list<string>  $overwrittenPoNumbers  the current revision replaced in place
     * @param  list<string>  $skippedPoNumbers  left exactly as they were
     */
    public function __construct(
        public PoImport $import,
        public array $revisedPoNumbers,
        public array $overwrittenPoNumbers,
        public array $skippedPoNumbers,
    ) {}

    /**
     * How many orders this decision actually wrote.
     */
    public function writtenCount(): int
    {
        return count($this->revisedPoNumbers) + count($this->overwrittenPoNumbers);
    }

    /**
     * Whether anything was destroyed, which the message has to say plainly.
     */
    public function hasOverwrites(): bool
    {
        return $this->overwrittenPoNumbers !== [];
    }

    /**
     * Whether the uploader declined every conflict — the cancel and discard path.
     */
    public function changedNothing(): bool
    {
        return $this->writtenCount() === 0;
    }
}
