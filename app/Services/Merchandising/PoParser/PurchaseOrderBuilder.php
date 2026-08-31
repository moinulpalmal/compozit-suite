<?php

namespace App\Services\Merchandising\PoParser;

use App\DataTransferObjects\Merchandising\Po\PackDto;
use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\Enums\Merchandising\ParserState;
use App\Services\Merchandising\PoParser\FieldExtractors\AddressBlockExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\FactoryExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\LineItemHeaderExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\LineItemRowExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\LogisticsExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\MasterDataExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\MiscCommentsExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\NotesExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\PackCommentsExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\PackCostExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\PageHeaderExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\ProductExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\ShipCommentsExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\SummaryTableExtractor;
use App\Services\Merchandising\PoParser\FieldExtractors\TariffExtractor;
use App\Services\Merchandising\PoParser\Support\Capture;
use App\Services\Merchandising\PoParser\Validators\PoDataValidator;

/**
 * Turns one purchase order's sections into a {@see PurchaseOrderDto}.
 *
 * Split from {@see ParserService} because the two jobs are different: that one takes
 * a *file* apart into pages and orders, this one takes *one order's* segments and
 * assembles a value object. Keeping them together meant a constructor holding both
 * the file toolchain and every field extractor.
 *
 * **Single-valued sections take the first segment; repeating ones take every
 * segment.** The page footer resets the state machine, so a header section recurs
 * once per printed page and only the first is the order's header. Packs are the
 * opposite: each is its own segment, and their order is what pairs a pack's costs
 * with its line items.
 */
final class PurchaseOrderBuilder
{
    public function __construct(
        private readonly PageHeaderExtractor $pageHeader,
        private readonly MasterDataExtractor $masterData,
        private readonly AddressBlockExtractor $addressBlock,
        private readonly NotesExtractor $notes,
        private readonly SummaryTableExtractor $summaryTable,
        private readonly LogisticsExtractor $logistics,
        private readonly FactoryExtractor $factory,
        private readonly ShipCommentsExtractor $shipComments,
        private readonly MiscCommentsExtractor $miscComments,
        private readonly ProductExtractor $product,
        private readonly TariffExtractor $tariff,
        private readonly PackCostExtractor $packCost,
        private readonly LineItemHeaderExtractor $lineItemHeader,
        private readonly LineItemRowExtractor $lineItemRow,
        private readonly PackCommentsExtractor $packComments,
        private readonly PoDataValidator $validator,
    ) {}

    /**
     * @param  list<array{state: ParserState, lines: list<array{index: int, text: string}>}>  $segments
     */
    public function build(array $segments, int $pageCount): PurchaseOrderDto
    {
        $byState = $this->groupByState($segments);

        $parts = [
            'header' => $this->pageHeader->build(
                array_column($this->first($byState, ParserState::PageHeader), 'text'),
                $pageCount,
            ),
            'masterData' => $this->masterData->build($this->text($byState, ParserState::MasterData)),
            'addresses' => $this->addressBlock->build($this->first($byState, ParserState::AddressBlock)),
            'notes' => $this->notes->build($this->text($byState, ParserState::Notes)),
            'summary' => $this->summaryTable->build($this->first($byState, ParserState::SummaryTable)),
            'logistics' => $this->logistics->build($this->text($byState, ParserState::Logistics)),
            'factory' => $this->factory->build($this->first($byState, ParserState::Factory)),
            'shipComments' => $this->shipComments->build($this->text($byState, ParserState::ShipComments)),
            'miscComments' => $this->miscComments->build($this->text($byState, ParserState::MiscComments)),
            'product' => $this->product->build($this->text($byState, ParserState::Product)),
            'tariffs' => $this->tariff->build($this->first($byState, ParserState::Tariff)),
            'packs' => $this->buildPacks($byState),
        ];

        // The order has to exist before it can be validated, and its warnings are
        // part of it — so it is assembled once to be checked and once to be returned.
        $provisional = new PurchaseOrderDto(...$parts);

        return new PurchaseOrderDto(...$parts, warnings: $this->validator->validate($provisional));
    }

    /**
     * Pair each pack's cost block with its line-item header, rows and comments.
     *
     * The four run in parallel and are matched by position — the nth cost block
     * belongs to the nth set of rows. A pack whose rows failed to parse still appears,
     * with an empty line-item list, which validation rule V5 then reports.
     *
     * @param  array<string, list<list<array{index: int, text: string}>>>  $byState
     * @return list<PackDto>
     */
    private function buildPacks(array $byState): array
    {
        $costBlocks = $this->all($byState, ParserState::PackCost);
        $headerBlocks = $this->all($byState, ParserState::LineItemHeader);
        $rowBlocks = $this->all($byState, ParserState::LineItemRows);
        $commentBlocks = $this->all($byState, ParserState::PackComments);

        $packs = [];

        foreach ($costBlocks as $index => $costLines) {
            $costs = $this->packCost->build($costLines);

            $header = isset($headerBlocks[$index])
                ? $this->lineItemHeader->build($headerBlocks[$index])
                : null;

            $lineItems = isset($rowBlocks[$index])
                ? $this->lineItemRow->build($rowBlocks[$index])
                : [];

            $packs[] = new PackDto(
                packNumber: $costs['pack_number'],
                packDescription: $costs['pack_description'],
                subclassFineline: $costs['subclass_fineline'],
                oldQsNumber: $costs['old_qs_number'],
                caseUpc: $costs['case_upc'],
                productDesc1: $costs['product_desc1'],
                assortmentId: $header?->get('assortment_id'),
                // A pack is one colour across a size run, so the colour of its first
                // line is the pack's colour.
                color: $lineItems[0]->color ?? null,
                vendorStock: $header?->get('vendor_stock'),
                costs: $costs['costs'],
                physical: $costs['physical'],
                lineItemHeader: $header,
                lineItems: $lineItems,
                comments: isset($commentBlocks[$index])
                    ? $this->packComments->build($commentBlocks[$index])
                    : null,
            );
        }

        return $packs;
    }

    /**
     * @param  list<array{state: ParserState, lines: list<array{index: int, text: string}>}>  $segments
     * @return array<string, list<list<array{index: int, text: string}>>>
     */
    private function groupByState(array $segments): array
    {
        $byState = [];

        foreach ($segments as $segment) {
            $byState[$segment['state']->value][] = $segment['lines'];
        }

        return $byState;
    }

    /**
     * @param  array<string, list<list<array{index: int, text: string}>>>  $byState
     * @return list<array{index: int, text: string}>
     */
    private function first(array $byState, ParserState $state): array
    {
        return $byState[$state->value][0] ?? [];
    }

    /**
     * @param  array<string, list<list<array{index: int, text: string}>>>  $byState
     * @return list<list<array{index: int, text: string}>>
     */
    private function all(array $byState, ParserState $state): array
    {
        return $byState[$state->value] ?? [];
    }

    /**
     * @param  array<string, list<list<array{index: int, text: string}>>>  $byState
     */
    private function text(array $byState, ParserState $state): string
    {
        return Capture::joinLines($this->first($byState, $state));
    }
}
