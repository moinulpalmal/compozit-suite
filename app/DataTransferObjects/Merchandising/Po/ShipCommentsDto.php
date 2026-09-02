<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The `Ship Comments` block — brand, forecast, and the testing and inspection
 * requirements the factory must meet.
 *
 * The booleans are presence tests against fixed phrases Walmart prints, so a
 * `false` means "the phrase was not found", not "Walmart said no". That
 * distinction matters when the template drifts: a renamed phrase silently turns
 * every requirement off, which is one of the things the template fingerprint on
 * the import exists to catch.
 */
final readonly class ShipCommentsDto
{
    /**
     * @param  array{segment: string, date: string|null}|null  $outOfStore
     * @param  list<int>  $poTypesInQuote
     * @param  array{required: bool, amount_usd: int|null}  $ctlLabtest
     * @param  array{required: bool, amount_usd: int|null}  $pli
     */
    public function __construct(
        public ?string $brandCode = null,
        public ?string $brandName = null,
        public ?string $pocoType = null,
        public ?int $annualForecastUnits = null,
        public ?array $outOfStore = null,
        public array $poTypesInQuote = [],
        public bool $rdcAligned = false,
        public bool $preProductionTestingRequired = false,
        public bool $productionTestingRequired = false,
        public array $ctlLabtest = ['required' => false, 'amount_usd' => null],
        public array $pli = ['required' => false, 'amount_usd' => null],
        public bool $sampleSubjectToApproval = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'brand_code' => $this->brandCode,
            'brand_name' => $this->brandName,
            'poco_type' => $this->pocoType,
            'annual_forecast_units' => $this->annualForecastUnits,
            'out_of_store' => $this->outOfStore,
            'po_types_in_quote' => $this->poTypesInQuote,
            'rdc_aligned' => $this->rdcAligned,
            'pre_production_testing_required' => $this->preProductionTestingRequired,
            'production_testing_required' => $this->productionTestingRequired,
            'ctl_labtest' => $this->ctlLabtest,
            'pli' => $this->pli,
            'sample_subject_to_approval' => $this->sampleSubjectToApproval,
        ];
    }
}
