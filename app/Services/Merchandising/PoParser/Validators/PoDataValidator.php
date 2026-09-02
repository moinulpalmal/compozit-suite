<?php

namespace App\Services\Merchandising\PoParser\Validators;

use App\DataTransferObjects\Merchandising\Po\PackDto;
use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\DataTransferObjects\Merchandising\Po\WarningDto;
use App\Enums\Merchandising\PoWarningSeverity;
use App\Services\Merchandising\BqsColourMatch;

/**
 * Checks a parsed purchase order against what a Walmart document should contain.
 *
 * **This is the one place that decides whether a missing value matters.** The
 * extractors deliberately return null for anything absent, so that a template which
 * has drifted produces a flagged import rather than an exception halfway through
 * reading it. The judgement is made once, here, where the rules can be read together.
 *
 * The rule codes V1–V12 are the document's own numbering, kept so a warning on screen
 * can be traced back to the rule that raised it. **Codes above V12 are this
 * application's**, for absences the document has no rule about but a merchandiser still
 * needs told: V13 and V14 below.
 *
 * Severity is the whole point of the split: an {@see PoWarningSeverity::Error} fails
 * the parse, a {@see PoWarningSeverity::Warning} only erodes confidence. A PO number
 * that is not ten digits means the wrong thing was read; a carton total that does not
 * add up means the document says something a merchandiser should look at.
 */
final class PoDataValidator
{
    /**
     * An assortment pack holds one line per size in the run.
     *
     * **Only assortment packs.** A pack the document marks `Single Item Pack` holds
     * exactly one line, and applying five to it warned on every pack of every
     * single-item order — five warnings an order, enough to push a clean parse under
     * `po-parser.parsing.warn_threshold` and grade it `needs_review` for nothing.
     * {@see self::expectedLineItems()}.
     */
    private const int EXPECTED_LINE_ITEMS_PER_PACK = 5;

    /** What the document calls a pack holding a single item rather than a size run. */
    private const string SINGLE_ITEM_PACK = 'single item pack';

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
            ...$this->checkLineItemColours($po),
            ...$this->checkPackSizes($po),
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
     * V5 — every pack carries the number of line items its assortment implies.
     *
     * @return list<WarningDto>
     */
    private function checkPackLineItems(PurchaseOrderDto $po): array
    {
        $warnings = [];

        foreach ($po->packs as $pack) {
            $count = count($pack->lineItems);
            $expected = $this->expectedLineItems($pack);

            if ($count === $expected) {
                continue;
            }

            $warnings[] = $this->warning($po, 'V5', PoWarningSeverity::Warning, 'packs.line_items',
                'Pack '.$pack->packNumber.' has '.$count.' line items (expected '.$expected.').');
        }

        return $warnings;
    }

    /**
     * How many lines this pack should hold, from what the document says it is.
     *
     * `Assortment Ind: Single Item Pack` is printed on the pack itself, so the answer
     * is read rather than assumed. A pack whose indicator could not be read falls back
     * to the size-run count, which is the document's general rule.
     */
    private function expectedLineItems(PackDto $pack): int
    {
        $indicator = $pack->lineItemHeader?->get('assortment_indicator');

        return is_string($indicator) && strtolower(trim($indicator)) === self::SINGLE_ITEM_PACK
            ? 1
            : self::EXPECTED_LINE_ITEMS_PER_PACK;
    }

    /**
     * V13 — every line item's colour was readable.
     *
     * A colour that did not parse is the most expensive absence in the document: it is
     * the field {@see BqsColourMatch} links on, so a null
     * colour cannot match a BQS row and cannot be mapped by hand either — the line just
     * reports as unlinked, which looks exactly like a colour the buyer never planned.
     *
     * There was no rule here once, and an infant purchase order whose every colour
     * parsed as null graded a clean `success` and said nothing at all.
     *
     * @return list<WarningDto>
     */
    private function checkLineItemColours(PurchaseOrderDto $po): array
    {
        $warnings = [];

        foreach ($po->packs as $pack) {
            $missing = 0;

            foreach ($pack->lineItems as $item) {
                if ($item->color === null || trim($item->color) === '') {
                    $missing++;
                }
            }

            if ($missing === 0) {
                continue;
            }

            $warnings[] = $this->warning($po, 'V13', PoWarningSeverity::Warning, 'packs.line_items.color',
                'Pack '.$pack->packNumber.' has '.$missing.' line item(s) with no colour, which cannot be '
                .'linked to a BQS row.');
        }

        return $warnings;
    }

    /**
     * V14 — a pack that printed no size column at all.
     *
     * Legitimate and common: a `Single Item Pack` is one garment, not a size run, and
     * the column is simply absent. It is still worth a word, because the same shape
     * results from a size column nobody could read — and the two are indistinguishable
     * afterwards. A person confirms which it was.
     *
     * Packs with no line items are left to V5; reporting both would say the same thing
     * twice.
     *
     * @return list<WarningDto>
     */
    private function checkPackSizes(PurchaseOrderDto $po): array
    {
        $warnings = [];

        foreach ($po->packs as $pack) {
            if ($pack->lineItems === []) {
                continue;
            }

            foreach ($pack->lineItems as $item) {
                if ($item->size !== null && trim($item->size) !== '') {
                    continue 2;
                }
            }

            $warnings[] = $this->warning($po, 'V14', PoWarningSeverity::Warning, 'packs.line_items.size',
                'Pack '.$pack->packNumber.' printed no size for any line item.');
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
