<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\ShipCommentsDto;
use App\Services\Merchandising\PoParser\Support\Capture;
use App\Services\Merchandising\PoParser\Support\DateParser;
use App\Services\Merchandising\PoParser\Support\NumberParser;

/**
 * Reads the `Ship Comments` block: brand, forecast, and testing/inspection requirements.
 *
 * Most of this block is free prose in which Walmart embeds fixed phrases, so the
 * requirements are presence tests. A `false` therefore means "the phrase was not
 * found" — which is the same answer for "not required" and for "Walmart reworded it".
 * The template fingerprint stored on each import is what surfaces the second case.
 */
final class ShipCommentsExtractor
{
    public function build(string $text): ShipCommentsDto
    {
        return new ShipCommentsDto(
            brandCode: Capture::text('/BRAND CODE:\s*(\d+)/', $text),
            brandName: Capture::text('/BRAND NAME:\s*(.+)$/m', $text),
            pocoType: Capture::text('/Wal-Mart type \((\w+)\)/', $text),
            annualForecastUnits: Capture::int('/ANNUAL FORECAST:\s*([\d,]+)\s*UNITS/', $text),
            outOfStore: $this->outOfStore($text),
            poTypesInQuote: $this->poTypesInQuote($text),
            rdcAligned: Capture::flag('/RDC ALIGNED/', $text),
            preProductionTestingRequired: Capture::flag('/PRE-?\s?PRODUCTION\s?TESTING REQUIRED/', $text),
            productionTestingRequired: Capture::flag('/PRODUCTION TESTING REQUIRED/', $text),
            ctlLabtest: $this->costedRequirement('/CTL LABTEST REQUIRED\s*US\$([\d,]+)/', $text),
            pli: $this->costedRequirement('/PLI REQUIRED\s*US\$([\d,]+)/', $text),
            sampleSubjectToApproval: Capture::flag('/SAMPLE SUBJECT TO BUYER APPROVAL/', $text),
        );
    }

    /**
     * @return array{segment: string, date: string|null}|null
     */
    private function outOfStore(string $text): ?array
    {
        if (preg_match('/OUT OF STORE DATE:\s*(SC\d+):(\d{2}\/\d{2}\/\d{4})/', $text, $matches) !== 1) {
            return null;
        }

        return [
            'segment' => $matches[1],
            'date' => DateParser::parse($matches[2]),
        ];
    }

    /**
     * The PO types this quote covers, printed as a `&`-joined run.
     *
     * @return list<int>
     */
    private function poTypesInQuote(string $text): array
    {
        if (preg_match('/PO TYPE\s+([\d &]+)\s+IN THIS QUOTE/', $text, $matches) !== 1) {
            return [];
        }

        if (preg_match_all('/\d+/', $matches[1], $numbers) === 0) {
            return [];
        }

        return array_map(intval(...), $numbers[0]);
    }

    /**
     * A requirement that carries a dollar amount when it applies.
     *
     * @return array{required: bool, amount_usd: int|null}
     */
    private function costedRequirement(string $pattern, string $text): array
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return ['required' => false, 'amount_usd' => null];
        }

        return [
            'required' => true,
            'amount_usd' => NumberParser::parseInt($matches[1]),
        ];
    }
}
