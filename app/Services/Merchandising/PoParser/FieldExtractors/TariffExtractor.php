<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\TariffDto;
use App\Services\Merchandising\PoParser\Support\Capture;
use App\Services\Merchandising\PoParser\Support\NumberParser;

/**
 * Reads the customs classification entries.
 *
 * The tariff section holds several entries with no separator between them — each one
 * simply begins with a `NNNN.NN.NN.NN` tariff number. So the segment is first split
 * on that pattern, then each entry's own lines are read as a block. A purchase order
 * normally carries two entries, one classified by the vendor and one by Walmart;
 * validation rule V12 warns when it does not.
 */
final class TariffExtractor
{
    /**
     * Simple labels whose value is the captured group, with how to read it.
     *
     * @var array<string, array{0: string, 1: 'str'|'float'}>
     */
    private const array LABELS = [
        'common_name' => ['/COMMON NAME:\s*(.+)$/m', 'str'],
        'product_desc' => ['/PRODUCT DESC:\s*(.+)$/m', 'str'],
        'classifier_type' => ['/CLASSIFIER TYPE:\s*(\w+)/', 'str'],
        'country_of_origin' => ['/COUNTRY OF ORIGIN:\s*([A-Z]{2})/', 'str'],
        'duty_type' => ['/DUTY TYPE:\s*(.+?)DUTY%/', 'str'],
        'duty_percent' => ['/DUTY%:\s*([\d.]+)%/', 'float'],
        'duty_weight_kg' => ['/DUTY WGT:\s*([\d.]+)\(KG\)/', 'float'],
        'duty_per_kg' => ['/DUTY\/KG:\s*([\d.]+)/', 'float'],
        'duty_per_un' => ['/DUTY\/UN:\s*([\d.]+)/', 'float'],
        'duty_qty' => ['/DUTY QTY:\s*([\d.]+)\(EA\)/', 'float'],
        'duty_per_ea' => ['/DUTY\/EA:\s*([\d.]+)/', 'float'],
        'factory_id' => ['/FACTORY:\s*(\d+)/', 'str'],
        'comments' => ['/Comments:\s*(.+)$/m', 'str'],
    ];

    /**
     * Labels whose cell is frequently blank, and which therefore need the
     * column-aware read in {@see self::valueAfterLabel()}.
     *
     * @var array<string, string>
     */
    private const array COLUMN_LABELS = [
        'agreement_item_no' => 'AGREEMENT ITEM NO:',
        'visa_number' => 'VISA NUMBER:',
        'national_customs_ruling' => 'NATIONAL CUSTOMS RULING #:',
        'special_authority' => 'SPECIAL AUTHORITY #:',
        'das_number' => 'D.A.S. NUMBER:',
        'epmt_number' => 'E.P.M.T. NUMBER:',
    ];

    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return list<TariffDto>
     */
    public function build(array $segmentLines): array
    {
        return array_map(
            fn (array $entry): TariffDto => $this->toDto($entry),
            $this->splitEntries($segmentLines),
        );
    }

    /**
     * Cut the segment at each tariff number.
     *
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return list<array{tariff: string, description: string, lines: list<string>}>
     */
    private function splitEntries(array $segmentLines): array
    {
        $entries = [];
        $current = null;

        foreach ($segmentLines as $line) {
            $text = $line['text'];

            if (preg_match('/^(\d{4}\.\d{2}\.\d{2}\.\d{2})\s+(.+)$/m', $text, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'tariff' => $matches[1],
                    'description' => trim($matches[2]),
                    'lines' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $text;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * @param  array{tariff: string, description: string, lines: list<string>}  $entry
     */
    private function toDto(array $entry): TariffDto
    {
        $text = implode("\n", $entry['lines']);

        $fields = [
            'tariff_number' => $entry['tariff'],
            'customs_description' => $entry['description'],
        ];

        foreach (self::LABELS as $key => [$pattern, $kind]) {
            $value = $kind === 'float'
                ? Capture::float($pattern, $text)
                : Capture::text($pattern, $text);

            // Only present labels become keys, so an entry's field set reflects what
            // Walmart actually classified rather than the union of everything.
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        foreach (self::COLUMN_LABELS as $key => $label) {
            $value = $this->valueAfterLabel($text, $label);

            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        $fiber = $this->fiberContent($text);

        if ($fiber !== null) {
            $fields['fiber_content'] = $fiber;
        }

        $duty = $this->dutyValue($text);

        if ($duty !== null) {
            $fields['duty_value'] = $duty;
        }

        if (Capture::flag('/DUTIABLE:\s*(?:Y|N)/', $text)) {
            $fields['dutiable'] = Capture::text('/DUTIABLE:\s*(Y|N)/', $text) === 'Y';
        }

        $fields['embedded_exchange_rate'] = Capture::float('/EXCHANGE RATE:\s*([\d.]+)/', $text);

        return new TariffDto($fields);
    }

    /**
     * @return array{pct: float, fiber: string}|null
     */
    private function fiberContent(string $text): ?array
    {
        if (preg_match('/([\d.]+)%\s+([A-Z][A-Z\s]+)$/', $text, $matches) !== 1) {
            return null;
        }

        return [
            'pct' => NumberParser::parse($matches[1]),
            'fiber' => trim($matches[2]),
        ];
    }

    /**
     * @return array{cnd: float, usd: float}|null
     */
    private function dutyValue(string $text): ?array
    {
        if (preg_match('/DUTY VALUE:\s*([\d.]+)CND\s*([\d.]+)USD/', $text, $matches) !== 1) {
            return null;
        }

        return [
            'cnd' => NumberParser::parse($matches[1]),
            'usd' => NumberParser::parse($matches[2]),
        ];
    }

    /**
     * Read a label whose cell is often empty, without swallowing the next column.
     *
     * These labels sit in a fixed-width row, so an empty cell runs straight into the
     * following label — a naive `(\S*)` capture would return `VISA` as the agreement
     * item number. The heuristic: whatever follows the label is only a value if it is
     * a *single* token. Anything that looks like a token followed by more text is the
     * next column, and the cell is empty.
     */
    private function valueAfterLabel(string $text, string $label): ?string
    {
        foreach (explode("\n", $text) as $line) {
            $position = strpos($line, $label);

            if ($position === false) {
                continue;
            }

            $after = trim(substr($line, $position + strlen($label)));

            if ($after === '') {
                return null;
            }

            if (preg_match('/^[A-Z0-9.\/\-]+\s+[A-Z]/', $after) === 1 || preg_match('/^\S+[ \t]+\S/', $after) === 1) {
                return null;
            }

            return $after;
        }

        return null;
    }
}
