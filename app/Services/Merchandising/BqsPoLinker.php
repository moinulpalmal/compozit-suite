<?php

namespace App\Services\Merchandising;

use App\Enums\Merchandising\BqsLinkSource;
use App\Models\Merchandising\BqsColourLink;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Connects purchase-order lines to the BQS rows that planned them.
 *
 * **The only writer of `po_line_items.bqs_row_id`.** Everything that could create,
 * move or clear a link goes through here, so the rules below hold on every path
 * rather than on the paths somebody remembered.
 *
 * ## Resolution order
 *
 * For a line item, in order, stopping at the first answer:
 *
 * 1. **An exact colour match** — {@see BqsColourMatch::matches()}, strict on both
 *    halves. Written as {@see BqsLinkSource::Auto}.
 * 2. **A standing manual decision** — {@see BqsColourLink} for this buyer, style and
 *    colour, resolved through the row key to whichever revision is current. Written as
 *    {@see BqsLinkSource::Manual}.
 * 3. Nothing. The line stays unlinked, which is a correct and common state: on the
 *    reference documents `TEAL-ICY MORN` has no BQS row at all.
 *
 * ## Rules, each of which is a decision rather than an accident
 *
 * - **Both import directions call this.** A purchase order routinely arrives before
 *   its BQS *and* after it, so {@see self::linkForPurchaseOrder()} and
 *   {@see self::linkForSheet()} both exist. Wiring only one means half the links never
 *   form, and the half that fails is invisible.
 * - **Never across buyers.** Neither `po_line_items` nor `bqs_rows` carries a
 *   `buyer_id` — both reach it through a parent (ARCHITECTURE.md §9.2) — so nothing at
 *   the database level stops a Walmart line pointing at a George row. Every candidate
 *   query below is constrained to one buyer, and that is the only thing preventing it.
 * - **Only current, only usable.** Candidates come from `BqsSheet::current()->usable()`;
 *   lines belonging to an order that is not `PurchaseOrder::usable()` are skipped. A
 *   failed parse is not order data.
 * - **Ambiguity is refused, not guessed.** Two current BQS rows matching one colour
 *   leaves the line unlinked — the same posture
 *   {@see BqsImportService::collidingSheet()} takes for a straddling workbook.
 * - **A manual link is never overwritten by the matcher.**
 *   {@see BqsLinkSource::isReplaceable()} is the guard.
 */
class BqsPoLinker
{
    /**
     * Link every line of one purchase order.
     *
     * Called after an import and after a staged import is resolved.
     */
    public function linkForPurchaseOrder(PurchaseOrder $order): int
    {
        if (! $order->parse_status->isUsable()) {
            return 0;
        }

        $candidates = $this->candidateRows($order->buyer_id);
        $mappings = $this->mappingsFor($order->buyer_id);

        $linked = 0;

        foreach ($order->lineItems()->get() as $line) {
            if ($this->resolve($line, $order->buyer_id, $candidates, $mappings)) {
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Link every unlinked purchase-order line this BQS could account for.
     *
     * The mirror of {@see self::linkForPurchaseOrder()}, for the case where the orders
     * were imported first — which is common, because a buyer sends the order
     * confirmation and the purchase orders on their own schedules.
     */
    public function linkForSheet(BqsSheet $sheet): int
    {
        $candidates = $this->candidateRows($sheet->buyer_id);
        $mappings = $this->mappingsFor($sheet->buyer_id);

        $linked = 0;

        foreach ($this->unlinkedLinesFor($sheet->buyer_id) as $line) {
            if ($this->resolve($line, $sheet->buyer_id, $candidates, $mappings)) {
                $linked++;
            }
        }

        return $linked;
    }

    /**
     * Record a person's decision about one colour, and apply it everywhere.
     *
     * The decision is stored as a {@see BqsColourLink} rule rather than as a fact about
     * these particular line items, so the next purchase order carrying the same colour
     * resolves without asking again. With strict colour matching that is not a
     * convenience — it is what stops the same decision being re-made forever.
     *
     * A null `$row` clears the mapping and unlinks the colour, so linking and
     * unlinking are one code path on the server as well as in the form.
     */
    public function link(PurchaseOrder $order, string $vendorStock, string $color, ?BqsRow $row): void
    {
        DB::transaction(function () use ($order, $vendorStock, $color, $row): void {
            $lines = $order->lineItems()
                ->where('vendor_stock', $vendorStock)
                ->where('color', $color);

            if (! $row instanceof BqsRow) {
                BqsColourLink::query()
                    ->where('buyer_id', $order->buyer_id)
                    ->where('vendor_style_no', $vendorStock)
                    ->where('po_color', $color)
                    ->delete();

                /*
                 * Clearing reaches exactly as far as linking did. A link made here was
                 * applied to every order of this buyer carrying the colour, so undoing
                 * it on this order alone would leave the others pointing at a decision
                 * that no longer exists — and no screen would show the disagreement.
                 */
                $this->linesFor($order->buyer_id, $vendorStock, $color)
                    ->where('bqs_link_source', BqsLinkSource::Manual->value)
                    ->update(['bqs_row_id' => null, 'bqs_link_source' => null]);

                return;
            }

            BqsColourLink::query()->updateOrCreate(
                [
                    'buyer_id' => $order->buyer_id,
                    'vendor_style_no' => $vendorStock,
                    'po_color' => $color,
                ],
                ['bqs_row_key' => $row->row_key],
            );

            $lines->update([
                'bqs_row_id' => $row->id,
                'bqs_link_source' => BqsLinkSource::Manual->value,
            ]);

            /*
             * The rule is not only about this order. Every other line of the same
             * buyer, style and colour that is still unlinked takes it too — including
             * orders imported long before the decision was made.
             */
            $this->applyMappingBeyond($order, $vendorStock, $color, $row);
        });
    }

    /**
     * Move links from one BQS revision to the next, matching on the row key.
     *
     * Used by `revise`, where both sheets exist at once.
     */
    public function carryForward(BqsSheet $from, BqsSheet $to): void
    {
        $this->restoreLinks($this->captureLinks($from), $to);
    }

    /**
     * Remember which purchase-order lines point at each row of a sheet.
     *
     * **`overwrite` deletes the held sheet before writing its replacement**, and
     * `po_line_items.bqs_row_id` is `nullOnDelete` — so by the time the new rows
     * exist, every link is already gone. Capturing first and restoring after is the
     * only order that survives it, and getting this backwards fails silently: the
     * import succeeds, and the BQS simply reports nothing ordered.
     *
     * @return array<string, list<int>> row key => line item ids
     */
    public function captureLinks(BqsSheet $sheet): array
    {
        $captured = [];

        foreach ($sheet->rows()->get() as $row) {
            $ids = PoLineItem::query()->where('bqs_row_id', $row->id)->pluck('id')->all();

            if ($ids !== []) {
                $captured[$row->row_key] = $ids;
            }
        }

        return $captured;
    }

    /**
     * Re-point captured links at the rows of a sheet, matching on the row key.
     *
     * A key with no counterpart in the new sheet means the buyer dropped that
     * colourway from the plan. The line keeps its order — that is still what was
     * ordered — but loses its link and its source, so it reads as unlinked rather than
     * as manually linked to nothing.
     *
     * @param  array<string, list<int>>  $captured
     */
    public function restoreLinks(array $captured, BqsSheet $to): void
    {
        $rows = $to->rows()->get()->keyBy('row_key');

        foreach ($captured as $rowKey => $lineIds) {
            $row = $rows->get($rowKey);

            PoLineItem::query()->whereIn('id', $lineIds)->update(
                $row instanceof BqsRow
                    ? ['bqs_row_id' => $row->id]
                    : ['bqs_row_id' => null, 'bqs_link_source' => null],
            );
        }
    }

    /**
     * The BQS rows a purchase-order colour may be linked to by hand.
     *
     * Restricted to the same buyer and the same vendor style, so a colour with no plan
     * behind it — `TEAL-ICY MORN` on the reference document — is offered nothing rather
     * than an invitation to attach it to an unrelated row.
     *
     * Sorted by {@see BqsColourMatch::affinity()}, which orders the list and nothing
     * else: with truncation, the row a person wants is almost always the one whose
     * Pantone name starts with what the document had room for.
     *
     * The shape is `components/ui/combobox.tsx`'s `ComboboxOption`, where `hint` is
     * optional rather than nullable — so a row with no colour variant omits the key
     * instead of sending `null`, which that type does not accept.
     *
     * @return list<array{value: int, label: string, hint?: string}>
     */
    public function candidatesFor(PurchaseOrder $order, string $vendorStock, ?string $color): array
    {
        return $this->candidateRows($order->buyer_id)
            ->where('vendor_style_no', $vendorStock)
            ->sortByDesc(fn (BqsRow $row): int => BqsColourMatch::affinity($color, $row))
            ->values()
            ->map(fn (BqsRow $row): array => [
                'value' => $row->id,
                'label' => trim("{$row->pantone_colour} ({$row->colour_family})"),
                ...($row->colour_variant === null ? [] : ['hint' => $row->colour_variant]),
            ])
            ->all();
    }

    /**
     * Apply the first rule that answers for this line, if any.
     *
     * @param  Collection<int, BqsRow>  $candidates
     * @param  Collection<string, BqsColourLink>  $mappings
     */
    private function resolve(PoLineItem $line, int $buyerId, Collection $candidates, Collection $mappings): bool
    {
        $source = $line->bqs_link_source;

        if ($source instanceof BqsLinkSource && ! $source->isReplaceable()) {
            return false;
        }

        $exact = $candidates
            ->where('vendor_style_no', $line->vendor_stock)
            ->filter(fn (BqsRow $row): bool => BqsColourMatch::matches($line->color, $row));

        /* Two current rows for one colour is a question, not something to guess. */
        if ($exact->count() === 1) {
            return $this->apply($line, $exact->first(), BqsLinkSource::Auto);
        }

        if ($exact->count() > 1) {
            return false;
        }

        $mapping = $mappings->get($this->mappingKey((string) $line->vendor_stock, (string) $line->color));

        if (! $mapping instanceof BqsColourLink) {
            return false;
        }

        $row = $candidates->firstWhere('row_key', $mapping->bqs_row_key);

        return $row instanceof BqsRow && $this->apply($line, $row, BqsLinkSource::Manual);
    }

    private function apply(PoLineItem $line, BqsRow $row, BqsLinkSource $source): bool
    {
        if ($line->bqs_row_id === $row->id && $line->bqs_link_source === $source) {
            return false;
        }

        $line->forceFill(['bqs_row_id' => $row->id, 'bqs_link_source' => $source->value])->save();

        return true;
    }

    /**
     * Apply a freshly made mapping to every other order of the same buyer.
     */
    private function applyMappingBeyond(PurchaseOrder $order, string $vendorStock, string $color, BqsRow $row): void
    {
        $this->linesFor($order->buyer_id, $vendorStock, $color)
            ->whereNull('bqs_row_id')
            ->update([
                'bqs_row_id' => $row->id,
                'bqs_link_source' => BqsLinkSource::Manual->value,
            ]);
    }

    /**
     * Every line of one buyer's orders carrying a style and colour.
     *
     * The buyer constraint goes through `purchase_orders`, because `po_line_items` has
     * no `buyer_id` of its own (ARCHITECTURE.md §9.2) — and without it this would
     * happily reach another buyer's identically-named style.
     *
     * @return Builder<PoLineItem>
     */
    private function linesFor(int $buyerId, string $vendorStock, string $color)
    {
        return PoLineItem::query()
            ->where('vendor_stock', $vendorStock)
            ->where('color', $color)
            ->whereIn(
                'purchase_order_id',
                PurchaseOrder::query()->withoutBuyerScope()->where('buyer_id', $buyerId)->select('id')
            );
    }

    /**
     * Every BQS row a line of this buyer could legitimately be linked to.
     *
     * `withoutBuyerScope()` with an explicit `buyer_id`: this runs from an import, and
     * an import has no authenticated actor when it is replayed from a queue or a
     * console command — the scope would then filter nothing and, worse, would filter
     * *differently* depending on who happened to be signed in. Naming the buyer is both
     * narrower and stable.
     *
     * @return Collection<int, BqsRow>
     */
    private function candidateRows(int $buyerId): Collection
    {
        return BqsRow::query()
            ->whereIn(
                'bqs_sheet_id',
                BqsSheet::query()->withoutBuyerScope()->current()->usable()
                    ->where('buyer_id', $buyerId)->select('id')
            )
            ->get();
    }

    /**
     * The standing manual decisions for this buyer, keyed for lookup.
     *
     * @return Collection<string, BqsColourLink>
     */
    private function mappingsFor(int $buyerId): Collection
    {
        return BqsColourLink::query()
            ->withoutBuyerScope()
            ->where('buyer_id', $buyerId)
            ->get()
            ->keyBy(fn (BqsColourLink $link): string => $this->mappingKey($link->vendor_style_no, $link->po_color));
    }

    /**
     * Unlinked lines of usable orders belonging to one buyer.
     *
     * @return Collection<int, PoLineItem>
     */
    private function unlinkedLinesFor(int $buyerId): Collection
    {
        return PoLineItem::query()
            ->whereNull('bqs_row_id')
            ->whereIn(
                'purchase_order_id',
                PurchaseOrder::query()->withoutBuyerScope()->usable()
                    ->where('buyer_id', $buyerId)->select('id')
            )
            ->get();
    }

    private function mappingKey(string $vendorStock, string $color): string
    {
        return BqsHeaderMap::normalise($vendorStock).'|'.BqsHeaderMap::normalise($color);
    }
}
