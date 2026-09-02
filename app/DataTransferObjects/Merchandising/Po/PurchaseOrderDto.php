<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * One complete purchase order, assembled from every section of its pages.
 *
 * A single uploaded file usually holds several of these — the parser groups pages
 * by PO number and builds one per group.
 *
 * This object's `toArray()` is the shape stored in `purchase_orders.payload` and
 * rendered by the front end, so its keys are a contract in two directions at once.
 * Changing one is a migration *and* a front-end change.
 */
final readonly class PurchaseOrderDto
{
    /**
     * @param  array{security: string|null, acceptance_clause: string|null}  $notes
     * @param  list<TariffDto>  $tariffs
     * @param  list<PackDto>  $packs
     * @param  list<WarningDto>  $warnings
     */
    public function __construct(
        public ?PoHeaderDto $header = null,
        public ?MasterDataDto $masterData = null,
        public ?AddressBlockDto $addresses = null,
        public array $notes = ['security' => null, 'acceptance_clause' => null],
        public ?SummaryDto $summary = null,
        public ?LogisticsDto $logistics = null,
        public ?FactoryDto $factory = null,
        public ?ShipCommentsDto $shipComments = null,
        public ?MiscCommentsDto $miscComments = null,
        public ?ProductDto $product = null,
        public array $tariffs = [],
        public array $packs = [],
        public array $warnings = [],
    ) {}

    /**
     * Every line item across every pack, which is what the `po_line_items` rows
     * are built from.
     *
     * @return list<LineItemDto>
     */
    public function allLineItems(): array
    {
        $items = [];

        foreach ($this->packs as $pack) {
            foreach ($pack->lineItems as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'header' => $this->header?->toArray(),
            'master_data' => $this->masterData?->toArray(),
            'addresses' => $this->addresses?->toArray(),
            'notes' => $this->notes,
            'summary' => $this->summary?->toArray(),
            'logistics' => $this->logistics?->toArray(),
            'factory' => $this->factory?->toArray(),
            'ship_comments' => $this->shipComments?->toArray(),
            'misc_comments' => $this->miscComments?->toArray(),
            'product' => $this->product?->toArray(),
            'tariffs' => array_map(
                static fn (TariffDto $tariff): array => $tariff->toArray(),
                $this->tariffs,
            ),
            'packs' => array_map(
                static fn (PackDto $pack): array => $pack->toArray(),
                $this->packs,
            ),
            'warnings' => array_map(
                static fn (WarningDto $warning): array => $warning->toArray(),
                $this->warnings,
            ),
        ];
    }
}
