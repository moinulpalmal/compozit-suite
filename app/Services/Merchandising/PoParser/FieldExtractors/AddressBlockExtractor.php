<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\AddressBlockDto;
use App\DataTransferObjects\Merchandising\Po\PartyAddressDto;
use App\Services\Merchandising\PoParser\LineProcessor\ColumnDetector;

/**
 * Reads the five-party address block, which is laid out in columns rather than rows.
 *
 * The block's first line is the dot guide that declares where each column begins;
 * every line under it is sliced at those offsets and the cells are collected downward,
 * so one *column* becomes one party. Reading it row-wise would interleave five
 * companies' addresses into nonsense.
 *
 * The block ends at the horizontal rule or at `Notes:`, whichever comes first — the
 * state machine's own transition is on `Notes:` alone, and the rule can precede it.
 */
final class AddressBlockExtractor
{
    public function __construct(
        private readonly ColumnDetector $columns,
    ) {}

    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     */
    public function build(array $segmentLines): AddressBlockDto
    {
        if ($segmentLines === []) {
            return new AddressBlockDto;
        }

        $starts = $this->columns->detect($segmentLines[0]['text']);

        if ($starts === []) {
            return new AddressBlockDto;
        }

        $parties = $this->partiesFrom($this->dataLines($segmentLines), $starts);

        return new AddressBlockDto(
            vendor: $parties[0] ?? null,
            bank: $parties[1] ?? null,
            primaryBeneficiary: $parties[2] ?? null,
            secondaryBeneficiary: $parties[3] ?? null,
            buyingAgent: $parties[4] ?? null,
        );
    }

    /**
     * The address lines under the guide, stopping at the end of the block.
     *
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return list<string>
     */
    private function dataLines(array $segmentLines): array
    {
        $lines = [];
        $count = count($segmentLines);

        for ($offset = 1; $offset < $count; $offset++) {
            $text = $segmentLines[$offset]['text'];

            if (preg_match('/^_+/', $text) === 1 || str_starts_with($text, 'Notes:')) {
                break;
            }

            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $dataLines
     * @param  list<int>  $starts
     * @return list<PartyAddressDto|null>
     */
    private function partiesFrom(array $dataLines, array $starts): array
    {
        /** @var list<list<string>> $columns */
        $columns = array_fill(0, count($starts), []);

        foreach ($dataLines as $line) {
            foreach ($this->columns->slice($line, $starts) as $index => $value) {
                // Blank cells are skipped rather than kept, so a party whose address
                // is shorter than its neighbour's does not gain empty lines.
                if ($value !== '') {
                    $columns[$index][] = $value;
                }
            }
        }

        $parties = [];

        foreach ($columns as $cells) {
            $parties[] = $cells === [] ? null : $this->party($cells);
        }

        return $parties;
    }

    /**
     * @param  list<string>  $cells
     */
    private function party(array $cells): PartyAddressDto
    {
        // The country is the last token of the last line — the one thing the block's
        // layout guarantees regardless of how many address lines a party has.
        $lastTokens = preg_split('/\s+/', trim($cells[count($cells) - 1])) ?: [];

        return new PartyAddressDto(
            name: $cells[0] ?? null,
            line1: $cells[1] ?? null,
            line2: $cells[2] ?? null,
            line3: $cells[3] ?? null,
            line4: $cells[4] ?? null,
            country: $lastTokens === [] ? null : $lastTokens[count($lastTokens) - 1],
            raw: implode(' | ', $cells),
        );
    }
}
