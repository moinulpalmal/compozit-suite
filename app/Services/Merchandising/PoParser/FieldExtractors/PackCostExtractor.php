<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\Services\Merchandising\PoParser\Support\Capture;
use App\Services\Merchandising\PoParser\Support\NumberParser;

/**
 * Reads a pack's identifiers, its cost stack and its physical attributes.
 *
 * **The cost stack is read by label, not by position.** Each row prints its own name
 * — `NET FIRST COST`, `Duty Amount` — followed by the figures, and Walmart omits the
 * rows that do not apply to a pack. Counting columns would therefore misalign the
 * moment a pack skips one; anchoring on the label cannot.
 *
 * Within a row the two currencies are taken as *the last two numbers on that line*
 * rather than the first two, because several rows print a percentage ahead of the
 * money and that percentage is not a currency figure.
 */
final class PackCostExtractor
{
    /**
     * Cost rows read as a plain currency pair.
     *
     * @var array<string, string>
     */
    private const array COST_ROWS = [
        'first_cost' => 'FIRST COST',
        'vendor_paid_freight' => 'Vendor Paid Freight',
        'net_first_cost' => 'NET FIRST COST',
        'dc_handling_fee' => 'DC Handling Fee',
        'duty_amount' => 'Duty Amount',
        'store_cost' => 'STORE COST',
    ];

    /**
     * Cost rows that also print a percentage.
     *
     * @var array<string, string>
     */
    private const array PERCENT_COST_ROWS = [
        'defective_allowance' => 'Defective Allowance %',
        'agent_commission' => 'In-Country Agent Commission',
    ];

    /**
     * Physical attributes, each a single number on its own label.
     *
     * @var array<string, string>
     */
    private const array PHYSICAL_NUMBERS = [
        'pack_cost' => '/Pack Cost:\s*([\d.]+)/',
        'cubic_meter' => '/Cubic Meter:\s*([\d.]+)/',
        'cubic_feet' => '/Cubic Feet:\s*([\d.]+)/',
        'weight_kgs' => '/Weight KGS:\s*([\d.]+)/',
        'weight_lbs' => '/Weight LBS:\s*([\d.]+)/',
        'length' => '/Pack Length:\s*([\d.]+)/',
        'width' => '/Pack Width:\s*([\d.]+)/',
        'height' => '/Pack Height:\s*([\d.]+)/',
        'each_wgt' => '/Each Wgt:\s*([\d.]+)/',
        'net_net_wgt' => '/Net Net Wgt:\s*([\d.]+)/',
        // The lookbehind keeps this from matching the "Net Net Wgt:" label above.
        'net_wgt' => '/(?<!Net )Net Wgt:\s*([\d.]+)/',
        'modular_length' => '/Modular Length:\s*([\d.]+)/',
        'modular_width' => '/Modular Width:\s*([\d.]+)/',
        'modular_height' => '/Modular Height:\s*([\d.]+)/',
    ];

    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return array<string, mixed>
     */
    public function build(array $segmentLines): array
    {
        $text = Capture::joinLines($segmentLines);

        return [
            'pack_description' => Capture::text('/Pack Description:\s*(.+?)SUBCLASS/', $text),
            'subclass_fineline' => Capture::text('/SUBCLASS\/FINELINE:\s*([\d\/]+)/', $text),
            'pack_number' => Capture::int('/Pack #:\s*(\d+)/', $text),
            'old_qs_number' => Capture::text('/Old Qs Nbr:\s*([\d-]+)/', $text),
            'case_upc' => Capture::text('/Case UPC:\s*(\d+)/', $text),
            'product_desc1' => Capture::text('/Product Desc1:\s*(.+?)\s{2,}/', $text),
            'product_desc2' => Capture::text('/Product Desc2:\s*(.+?)\s{2,}/', $text),
            'patent_info' => Capture::text('/Patent Info:\s*(.+?)\s{2,}/', $text),
            'inspection_code' => Capture::text('/Inspection Code:\s*(\S+)/', $text),
            'costs' => $this->costs($text),
            'physical' => $this->physical($text),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function costs(string $text): array
    {
        $costs = [];

        foreach (self::COST_ROWS as $key => $label) {
            $costs[$key] = $this->currencyPair($text, $label);
        }

        foreach (self::PERCENT_COST_ROWS as $key => $label) {
            $costs[$key] = $this->percentCurrencyPair($text, $label);
        }

        $costs['markup_pct'] = Capture::float('/MARKUP%\s+([\d.]+)/', $text);
        $costs['retail'] = Capture::float('/RETAIL\s+([\d.]+)/', $text);
        $costs['exchange_rate'] = Capture::float('/Exchange Rate:\s*1 USD=\s*([\d.]+) CND/', $text);

        return $costs;
    }

    /**
     * @return array<string, mixed>
     */
    private function physical(string $text): array
    {
        $physical = [];

        $packType = $this->packType($text);

        if ($packType !== null) {
            $physical['pack_type'] = $packType;
        }

        $qty = Capture::int('/Qty \(ea\):\s*(\d+)/', $text);

        if ($qty !== null) {
            $physical['qty_ea'] = $qty;
        }

        foreach (self::PHYSICAL_NUMBERS as $key => $pattern) {
            $value = Capture::float($pattern, $text);

            if ($value !== null) {
                $physical[$key] = $value;
            }
        }

        foreach (['pallet_ti' => '/Pallet Ti:\s*(\d+)/', 'pallet_hi' => '/Pallet Hi:\s*(\d+)/'] as $key => $pattern) {
            $value = Capture::int($pattern, $text);

            if ($value !== null) {
                $physical[$key] = $value;
            }
        }

        return $physical;
    }

    /**
     * The pack type, which prints as several space-separated descriptors.
     *
     * Bounded by the `Product #` label when one follows on the same row, and by the
     * end of the line otherwise.
     *
     * @return list<string>|null
     */
    private function packType(string $text): ?array
    {
        $raw = Capture::text('/Pack Type:\s*(.+?)\s{2,}Product #/', $text)
            ?? Capture::text('/Pack Type:\s*(.+)$/m', $text);

        if ($raw === null) {
            return null;
        }

        $parts = array_map(trim(...), preg_split('/\s{2,}/', $raw) ?: []);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{usd: float|null, cnd: float|null}
     */
    private function currencyPair(string $text, string $label): array
    {
        $numbers = $this->numbersAfterLabel($text, $label);
        $count = count($numbers);

        if ($count < 2) {
            return ['usd' => null, 'cnd' => null];
        }

        return [
            'usd' => $numbers[$count - 2],
            'cnd' => $numbers[$count - 1],
        ];
    }

    /**
     * @return array{usd: float|null, cnd: float|null, pct: float|null}
     */
    private function percentCurrencyPair(string $text, string $label): array
    {
        $pair = $this->currencyPair($text, $label);
        $pair['pct'] = Capture::float('/'.preg_quote($label, '/').'\s+(\.?\d+)%/', $text);

        return $pair;
    }

    /**
     * Every number printed to the right of a label, on that label's own line.
     *
     * The `\.?` prefix accepts the leading-dot form Walmart uses for values below one.
     *
     * @return list<float>
     */
    private function numbersAfterLabel(string $text, string $label): array
    {
        $tail = null;

        foreach (explode("\n", $text) as $line) {
            $position = strpos($line, $label);

            if ($position !== false) {
                $tail = substr($line, $position + strlen($label));
                break;
            }
        }

        if ($tail === null) {
            return [];
        }

        if (preg_match_all('/\.?\d[\d,]*\.?\d*/', $tail, $matches) === 0) {
            return [];
        }

        return array_map(
            static fn (string $number): float => NumberParser::parse($number),
            $matches[0],
        );
    }
}
