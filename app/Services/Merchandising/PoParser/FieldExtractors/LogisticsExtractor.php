<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\LogisticsDto;
use App\Enums\Merchandising\PoType;
use App\Services\Merchandising\PoParser\Support\Capture;
use App\Services\Merchandising\PoParser\Support\NumberParser;

/**
 * Reads the shipping and payment block: ports, dates, incoterm, container, terms.
 *
 * Thirty labels in a table rather than thirty method calls, because they differ only
 * in pattern and type — and a table is what makes adding the thirty-first a one-line
 * change rather than a copy-paste.
 *
 * **Every key is emitted, with null where the label was absent.** Callers and the
 * stored payload therefore have a stable shape, and "Walmart stopped printing this"
 * is visible as a null rather than as a missing key that reads identically to a typo.
 */
final class LogisticsExtractor
{
    /**
     * Label patterns, each with how its captured value should be read.
     *
     * `\s{2,}` bounds a value with another column to its right; `(.+)$/m` is for one
     * that runs to the end of its line.
     *
     * @var array<string, array{0: string, 1: 'date'|'int'|'str'}>
     */
    private const array LABELS = [
        'whse_ship_date' => ['/Whse Ship Date:\s*(\d{2}\/\d{2}\/\d{4})/', 'date'],
        'whse_cancel_date' => ['/Whse Cancel Date:\s*(\d{2}\/\d{2}\/\d{4})/', 'date'],
        'loading_port' => ['/Loading Port:\s*(.+?)\s{2,}/', 'str'],
        'discharge_port' => ['/Discharge Port:\s*(.+?)\s{2,}/', 'str'],
        'entry_port' => ['/Entry Port:\s*(.+?)\s{2,}/', 'str'],
        'transport_mode' => ['/Transport Mode:\s*(.+?)\s{2,}/', 'str'],
        'warehouse_number' => ['/Warehouse Nmbr:\s*(\d+)/', 'str'],
        'container_size' => ['/Container Size:\s*(.+?)\s{2,}/', 'str'],
        'ship_number' => ['/Ship Nbr:\s*(\d+)/', 'int'],
        'country_of_origin' => ['/Country of origin:\s*(.+?)\s{2,}/', 'str'],
        'place_of_possession' => ['/Place of Possession:\s*(.+)$/m', 'str'],
        'consolidator' => ['/Consolidator:\s*(.+?)\s{2,}/', 'str'],
        'incoterm' => ['/Incoterm:\s*([A-Z]+)/', 'str'],
        'deconsolidator' => ['/Deconsolidator:\s*(.+?)\s{2,}/', 'str'],
        'broker' => ['/Broker:\s*(.+?)\s{2,}/', 'str'],
        'season' => ['/Season:\s*(\d+)/', 'str'],
        'event_code' => ['/Event:\s*(\S+)/', 'str'],
        'in_store_date' => ['/In Store Date:\s*(\d{2}\/\d{2}\/\d{4})/', 'date'],
        'wmt_week' => ['/WMT Week:\s*(\d+)/', 'str'],
        'otb' => ['/OTB:\s*(\S+)/', 'str'],
        'payment_method' => ['/Payment Method:\s*(.+?)\s{2,}/', 'str'],
        'net_days_due' => ['/Net Days Due:\s*(\d+)/', 'int'],
        'business_format' => ['/Business Format:\s*(\S+)/', 'str'],
        'sample' => ['/Sample:\s*([A-Z])/', 'str'],
        'purchase_order_ref' => ['/Purchase Order:\s*(\d{10})/', 'str'],
        'storage_facility' => ['/Storage Facility:\s*(.+?)\s{2,}/', 'str'],
        'in_storage_date' => ['/In Storage Date:\s*(\d{2}\/\d{2}\/\d{4})/', 'date'],
        'storage_day_count' => ['/Storage Day Count:\s*(\d+)/', 'str'],
        'payment_ref' => ['/Payment Ref:\s*(.+?)\s{2,}/', 'str'],
    ];

    public function build(string $text): LogisticsDto
    {
        $fields = [];

        foreach (self::LABELS as $key => [$pattern, $kind]) {
            $fields[$key] = match ($kind) {
                'date' => Capture::date($pattern, $text),
                'int' => Capture::int($pattern, $text),
                'str' => Capture::text($pattern, $text),
            };
        }

        /*
         * `po_type` is read outside the label table because it is the one field
         * with a consumer beyond display: the import promotes it to a column, and
         * `PoType::fromCode()` needs a genuinely typed int rather than whatever the
         * table's `match` happened to produce.
         */
        $poType = Capture::int('/PO Type:\s*(\d+)/', $text);

        $fields['po_type'] = $poType;
        $fields['po_type_label'] = PoType::fromCode($poType)->label();
        $fields['total_duty_exp'] = $this->totalDutyExpense($text);

        return new LogisticsDto($fields);
    }

    /**
     * The duty total, printed as both currencies on one line.
     *
     * The leading `\.?` matters: a value below one dollar is printed without its
     * leading zero, so `.75CND` is a real token this must accept.
     *
     * @return array{cnd: float|null, usd: float|null}
     */
    private function totalDutyExpense(string $text): array
    {
        if (preg_match('/Total Duty Exp:\s*\.?([\d.]+)CND\s+\.?([\d.]+)USD/', $text, $matches) !== 1) {
            return ['cnd' => null, 'usd' => null];
        }

        return [
            'cnd' => NumberParser::parse($matches[1]),
            'usd' => NumberParser::parse($matches[2]),
        ];
    }
}
