<?php

namespace App\Services\Merchandising\PoParser\StateMachine;

use App\Enums\Merchandising\ParserState;
use App\Services\Merchandising\PoParser\ParserService;
use App\Services\Merchandising\PoParser\Support\RegexLibrary;

/**
 * Cuts a purchase order's lines into the sections the extractors read.
 *
 * The document has no markup, so a section is recognised by the line that opens it.
 * The table below is that knowledge: from a given section, which line starts which
 * next one. A section not listed as reachable from the current one cannot be entered,
 * which is what stops a stray `FACTORY:` inside a comment block from derailing the
 * parse.
 *
 * Two properties are worth knowing:
 *
 * - **The page footer resets to {@see ParserState::PageHeader}.** Every page repeats
 *   the banner, so a document of forty pages re-enters the header state forty times.
 *   That is why {@see ParserService} takes the
 *   *first* segment of the single-valued sections and *every* segment of the
 *   repeating ones (packs).
 * - **The same state can be entered many times**, and each entry is its own segment.
 *   Four packs produce four `PackCost` segments, and their order is what pairs them
 *   with their line items.
 */
final class SectionStateMachine
{
    /**
     * From each state, the patterns that leave it and where each one goes.
     *
     * @var array<string, list<array{0: string, 1: ParserState}>>
     */
    private array $transitions;

    public function __construct()
    {
        $this->transitions = [
            ParserState::PageHeader->value => [
                ['/^\s*Division:/', ParserState::MasterData],
                ['/^PRODUCT:/', ParserState::Product],
                ['/^Item \(L x W x H\):/', ParserState::LineItemHeader],
            ],
            ParserState::MasterData->value => [
                [RegexLibrary::GUIDE_LINE, ParserState::AddressBlock],
            ],
            ParserState::AddressBlock->value => [
                ['/^Notes:/', ParserState::Notes],
            ],
            ParserState::Notes->value => [
                ['/^DESTINATION\s+VENDOR/', ParserState::SummaryTable],
            ],
            ParserState::SummaryTable->value => [
                ['/^\s*Whse Ship Date:/', ParserState::Logistics],
            ],
            ParserState::Logistics->value => [
                ['/^FACTORY:/', ParserState::Factory],
            ],
            ParserState::Factory->value => [
                ['/^Ship Comments:/', ParserState::ShipComments],
            ],
            ParserState::ShipComments->value => [
                ['/^Misc comments/', ParserState::MiscComments],
            ],
            ParserState::Product->value => [
                ['/^TARIFF#/', ParserState::Tariff],
            ],
            ParserState::Tariff->value => [
                ['/^Pack Description:/', ParserState::PackCost],
            ],
            ParserState::LineItemHeader->value => [
                [RegexLibrary::LINE_ITEM_COLUMNS, ParserState::LineItemRows],
            ],
            ParserState::LineItemRows->value => [
                ['/^\s*PACK COMMENTS:/', ParserState::PackComments],
            ],
        ];
    }

    /**
     * @param  list<array{index: int, text: string}>  $poLines
     * @return list<array{state: ParserState, lines: list<array{index: int, text: string}>}>
     */
    public function run(array $poLines): array
    {
        $segments = [];
        $state = ParserState::PageHeader;
        $current = ['state' => $state, 'lines' => []];

        foreach ($poLines as $line) {
            $text = $line['text'];

            if (str_contains($text, RegexLibrary::FOOTER_TEXT)) {
                if ($current['lines'] !== []) {
                    $segments[] = $current;
                }

                $state = ParserState::PageHeader;
                $current = ['state' => $state, 'lines' => []];

                continue;
            }

            $next = $this->transition($state, $text);

            if ($next instanceof ParserState && $next !== $state) {
                if ($current['lines'] !== []) {
                    $segments[] = $current;
                }

                $state = $next;
                $current = ['state' => $state, 'lines' => []];
            }

            $current['lines'][] = $line;
        }

        if ($current['lines'] !== []) {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * The state this line moves us to, or null to stay put.
     */
    private function transition(ParserState $state, string $text): ?ParserState
    {
        foreach ($this->transitions[$state->value] ?? [] as [$pattern, $target]) {
            if (preg_match($pattern, $text) === 1) {
                return $target;
            }
        }

        return null;
    }
}
