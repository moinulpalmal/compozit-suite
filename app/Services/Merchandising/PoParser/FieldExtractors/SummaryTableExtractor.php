<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\SummaryDto;
use App\Services\Merchandising\PoParser\Support\DateParser;
use App\Services\Merchandising\PoParser\Support\NumberParser;
use App\Services\Merchandising\PoParser\Support\RegexLibrary;

/**
 * Reads the totals table, which prints one order across two rows.
 *
 * The table has no cell labels — position is the only thing that says which figure is
 * which. So the two rows carrying dates are located first, then their numeric tokens
 * are read in order: on the first row, cartons, quantity, volume and weight, followed
 * by the Canadian-dollar half of the cost stack; on the second, the US-dollar half.
 *
 * Dates and the `(feet)` volume are stripped before the numbers are counted, because
 * both would otherwise contribute digits and shift every index after them. That
 * removal is the load-bearing step in this file.
 */
final class SummaryTableExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     */
    public function build(array $segmentLines): SummaryDto
    {
        $dateRows = [];

        foreach ($segmentLines as $line) {
            if (preg_match(RegexLibrary::DATE_MDY, $line['text']) === 1) {
                $dateRows[] = $line['text'];
            }
        }

        if (count($dateRows) < 2) {
            return new SummaryDto;
        }

        [$firstRow, $secondRow] = $dateRows;

        $cndTokens = $this->numericTokens($firstRow);
        $usdTokens = $this->numericTokens($secondRow);

        // All four totals come from fixed positions on the first row, so a short row
        // means the positions cannot be trusted and none of them is read. Taking the
        // ones that happen to be present would silently mislabel them.
        $hasTotals = count($cndTokens) >= 4;

        return new SummaryDto(
            destination: $this->destinationFrom($firstRow),
            vendorShipDate: $this->firstDate($firstRow),
            cancelDate: $this->firstDate($secondRow),
            masterCartons: $hasTotals ? (int) $cndTokens[0] : null,
            quantityEa: $hasTotals ? (int) $cndTokens[1] : null,
            totalVolumeCbm: $hasTotals ? $cndTokens[2] : null,
            totalWeightKgs: $hasTotals ? $cndTokens[3] : null,
            totalVolumeCubicFeet: $this->cubicFeet($secondRow),
            netFirstCost: $this->pair($cndTokens, $usdTokens, 4, 0),
            // Landed cost is not printed in this table; it is kept in the shape so
            // the payload's cost stack has one key per row the document defines.
            landedCost: ['cnd' => null, 'usd' => null],
            storeCost: $this->pair($cndTokens, $usdTokens, 5, 1),
            retailCost: $this->pair($cndTokens, $usdTokens, 6, 2),
            storeGrossMargin: $this->pair($cndTokens, $usdTokens, 7, 3),
        );
    }

    private function destinationFrom(string $row): ?string
    {
        if (preg_match('/^([A-Z][A-Z\s\-\.]*,\s*[A-Z][A-Z\s\-\.]*)/', trim($row), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function firstDate(string $row): ?string
    {
        if (preg_match(RegexLibrary::DATE_MDY, $row, $matches) !== 1) {
            return null;
        }

        return DateParser::parse($matches[0]);
    }

    private function cubicFeet(string $row): ?float
    {
        if (preg_match(RegexLibrary::CUBIC_FEET, $row, $matches) !== 1) {
            return null;
        }

        return NumberParser::parse($matches[1]);
    }

    /**
     * Every number on a row, with the tokens that would corrupt the ordering removed.
     *
     * @return list<float>
     */
    private function numericTokens(string $row): array
    {
        $row = (string) preg_replace('/\d{2}\/\d{2}\/\d{4}/', ' ', $row);
        $row = (string) preg_replace('/[\d,.]+\(feet\)/', ' ', $row);

        if (preg_match_all(RegexLibrary::NUMERIC_TOKEN, $row, $matches) === 0) {
            return [];
        }

        return array_map(
            static fn (string $token): float => NumberParser::parse($token),
            $matches[1],
        );
    }

    /**
     * One cost row, read from its own index on each of the two currency rows.
     *
     * @param  list<float>  $cndTokens
     * @param  list<float>  $usdTokens
     * @return array{cnd: float|null, usd: float|null}
     */
    private function pair(array $cndTokens, array $usdTokens, int $cndIndex, int $usdIndex): array
    {
        return [
            'cnd' => $cndTokens[$cndIndex] ?? null,
            'usd' => $usdTokens[$usdIndex] ?? null,
        ];
    }
}
