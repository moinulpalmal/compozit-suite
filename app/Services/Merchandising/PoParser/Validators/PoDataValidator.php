<?php

namespace App\Services\Merchandising\PoParser\Validators;

use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\DataTransferObjects\Merchandising\Po\WarningDto;
use App\Enums\Merchandising\PoWarningSeverity;

/**
 * Checks a parsed purchase order against what a Walmart document should contain.
 *
 * **This is the one place that decides whether a missing value matters.** The
 * extractors deliberately return null for anything absent, so that a template which
 * has drifted produces a flagged import rather than an exception halfway through
 * reading it. The judgement is made once, here, where the rules can be read together.
 *
 * The rule codes (V1, V3, V5, V12) are the document's own numbering, kept so a
 * warning on screen can be traced back to the rule that raised it.
 *
 * Severity is the whole point of the split: an {@see PoWarningSeverity::Error} fails
 * the parse, a {@see PoWarningSeverity::Warning} only erodes confidence. A PO number
 * that is not ten digits means the wrong thing was read; a carton total that does not
 * add up means the document says something a merchandiser should look at.
 */
final class PoDataValidator
{
    /** A pack is expected to hold one line per size in the run. */
    private const int EXPECTED_LINE_ITEMS_PER_PACK = 5;

    /** One classification by the vendor, one by Walmart. */
    private const int EXPECTED_TARIFF_ENTRIES = 2;

    /**
     * @return list<WarningDto>
     */
    public function validate(PurchaseOrderDto $po): array
    {
        return [
            ...$this->checkPoNumber($po),
            ...$this->checkQuoteId($po),
            ...$this->checkMasterCartons($po),
            ...$this->checkPackLineItems($po),
            ...$this->checkTariffs($po),
        ];
    }

    /**
     * V1 — the PO number is ten digits.
     *
     * @return list<WarningDto>
     */
    private function checkPoNumber(PurchaseOrderDto $po): array
    {
        if (preg_match('/^\d{10}$/', (string) $po->header?->poNumber) === 1) {
            return [];
        }

        return [$this->warning($po, 'V1', PoWarningSeverity::Error, 'header.po_number',
            'PO number is missing or is not ten digits.')];
    }

    /**
     * V2 — a quote id is present.
     *
     * @return list<WarningDto>
     */
    private function checkQuoteId(PurchaseOrderDto $po): array
    {
        if ($po->header?->quoteId !== null) {
            return [];
        }

        return [$this->warning($po, 'V2', PoWarningSeverity::Error, 'header.quote_id',
            'Quote ID is missing.')];
    }

    /**
     * V3 — the order's master-carton total equals the sum of its packs'.
     *
     * Skipped when no pack states a carton count, since zero would then compare
     * against the order total and warn on every document.
     *
     * @return list<WarningDto>
     */
    private function checkMasterCartons(PurchaseOrderDto $po): array
    {
        $stated = $po->summary?->masterCartons;

        if ($stated === null) {
            return [];
        }

        $summed = 0;

        foreach ($po->packs as $pack) {
            $summed += (int) $pack->lineItemHeader?->get('total_cartons_per_line', 0);
        }

        if ($summed === 0 || $summed === $stated) {
            return [];
        }

        return [$this->warning($po, 'V3', PoWarningSeverity::Warning, 'summary.master_cartons',
            "Master cartons {$stated} does not equal the sum of pack cartons {$summed}.")];
    }

    /**
     * V5 — every pack carries the expected number of line items.
     *
     * @return list<WarningDto>
     */
    private function checkPackLineItems(PurchaseOrderDto $po): array
    {
        $warnings = [];

        foreach ($po->packs as $pack) {
            $count = count($pack->lineItems);

            if ($count === self::EXPECTED_LINE_ITEMS_PER_PACK) {
                continue;
            }

            $warnings[] = $this->warning($po, 'V5', PoWarningSeverity::Warning, 'packs.line_items',
                'Pack '.$pack->packNumber.' has '.$count.' line items (expected '
                .self::EXPECTED_LINE_ITEMS_PER_PACK.').');
        }

        return $warnings;
    }

    /**
     * V12 — the order carries both tariff classifications.
     *
     * @return list<WarningDto>
     */
    private function checkTariffs(PurchaseOrderDto $po): array
    {
        if (count($po->tariffs) === self::EXPECTED_TARIFF_ENTRIES) {
            return [];
        }

        return [$this->warning($po, 'V12', PoWarningSeverity::Warning, 'tariffs',
            'Expected '.self::EXPECTED_TARIFF_ENTRIES.' tariff entries (VENDOR and WALMART), found '
            .count($po->tariffs).'.')];
    }

    private function warning(
        PurchaseOrderDto $po,
        string $code,
        PoWarningSeverity $severity,
        string $field,
        string $message,
    ): WarningDto {
        return new WarningDto(
            code: $code,
            severity: $severity,
            field: $field,
            poNumber: $po->header?->poNumber,
            page: null,
            lineIndex: null,
            message: $message,
        );
    }
}
