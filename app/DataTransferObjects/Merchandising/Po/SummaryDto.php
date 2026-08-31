<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The order's totals: cartons, quantity, volume, weight and the cost stack.
 *
 * The costs are printed as two rows — Canadian dollars above, US dollars below —
 * so each one is a `['cnd' => float|null, 'usd' => float|null]` pair rather than a
 * scalar. `$masterCartons` is the figure validation rule V3 checks against the sum
 * of the packs' own carton counts; a mismatch is a warning, because the document is
 * the authority and a disagreement is something a merchandiser must see rather
 * than something the parser should silently reconcile.
 */
final readonly class SummaryDto
{
    /**
     * @param  array{cnd: float|null, usd: float|null}  $netFirstCost
     * @param  array{cnd: float|null, usd: float|null}  $landedCost
     * @param  array{cnd: float|null, usd: float|null}  $storeCost
     * @param  array{cnd: float|null, usd: float|null}  $retailCost
     * @param  array{cnd: float|null, usd: float|null}  $storeGrossMargin
     */
    public function __construct(
        public ?string $destination = null,
        public ?string $vendorShipDate = null,
        public ?string $cancelDate = null,
        public ?int $masterCartons = null,
        public ?int $quantityEa = null,
        public ?float $totalVolumeCbm = null,
        public ?float $totalVolumeCubicFeet = null,
        public ?float $totalWeightKgs = null,
        public array $netFirstCost = ['cnd' => null, 'usd' => null],
        public array $landedCost = ['cnd' => null, 'usd' => null],
        public array $storeCost = ['cnd' => null, 'usd' => null],
        public array $retailCost = ['cnd' => null, 'usd' => null],
        public array $storeGrossMargin = ['cnd' => null, 'usd' => null],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'destination' => $this->destination,
            'vendor_ship_date' => $this->vendorShipDate,
            'cancel_date' => $this->cancelDate,
            'master_cartons' => $this->masterCartons,
            'quantity_ea' => $this->quantityEa,
            'total_volume_cbm' => $this->totalVolumeCbm,
            'total_volume_cubic_feet' => $this->totalVolumeCubicFeet,
            'total_weight_kgs' => $this->totalWeightKgs,
            'net_first_cost' => $this->netFirstCost,
            'landed_cost' => $this->landedCost,
            'store_cost' => $this->storeCost,
            'retail_cost' => $this->retailCost,
            'store_gross_margin' => $this->storeGrossMargin,
        ];
    }
}
