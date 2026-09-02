<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connects a purchase-order line to the BQS row that planned it.
 *
 * ## What the link is for
 *
 * A BQS row is what the buyer *planned* to buy — one vendor style in one colourway.
 * A purchase-order line is what they actually *ordered*. Joining them is what lets a
 * merchandiser ask "has this plan been ordered, and in full?", and gives Production a
 * path from an order back to the plan behind it.
 *
 * The link lives here rather than on a pivot table because a line item belongs to at
 * most one BQS row, while a BQS row has many lines — five sizes per pack, several
 * packs per order, and several orders per plan. A nullable foreign key says exactly
 * that; a pivot would only be a way to express a cardinality the data does not have.
 *
 * ## `total_cartons_per_line` is the whole reason the arithmetic works
 *
 * **`po_line_items.quantity` is the size ratio inside one pack, not an ordered
 * quantity.** On the reference document `3 + 4 + 4 + 2 + 1 = 14`, which is the pack
 * ("14PC GR SS SKATER DRESS"), and `Total Cartons per Line: 393` is how many of those
 * packs were ordered. Ordered units are the product:
 *
 * ```text
 * 14 × 393 = 5,502 = the BQS row's Initial Set Units / Store, exactly
 * ```
 *
 * Summing `quantity` alone reports 14 against a plan of 5,502 — wrong by a factor of
 * 393, and wrong in the direction that looks like a catastrophic shortfall. The
 * multiplier is parsed into each pack's `line_item_header` and therefore lives only in
 * `purchase_orders.payload`, which is not portably queryable across MySQL and SQLite.
 * So it is denormalised onto the line, for the same reason `vendor_stock` already is.
 *
 * ## Indexing
 *
 * `(vendor_stock, color)` serves the BQS-side matcher, which looks for unlinked lines
 * of a style and colour whenever a BQS is imported — a real query, not an imagined one
 * (ARCHITECTURE.md §6.3). `bqs_row_id` is `constrained()` and InnoDB indexes it
 * automatically. `bqs_link_source` is two values and is never a leading predicate, so
 * it is deliberately **not** indexed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('po_line_items', function (Blueprint $table): void {
            /*
             * `nullOnDelete` rather than a cascade: losing the BQS a line was planned
             * against does not make the *order* untrue. The line survives, unlinked.
             */
            $table->foreignId('bqs_row_id')->nullable()->after('id')
                ->constrained('bqs_rows')->nullOnDelete();

            /*
             * Whether a person decided this or the matcher did. It is what stops a
             * re-import quietly overwriting someone's judgement — see `BqsPoLinker`.
             */
            $table->string('bqs_link_source', 10)->nullable()->after('bqs_row_id');

            /* The multiplier — see the docblock. */
            $table->unsignedInteger('total_cartons_per_line')->nullable()->after('quantity');

            $table->index(['vendor_stock', 'color']);
        });

        $this->backfillCartonsPerLine();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('po_line_items', function (Blueprint $table): void {
            $table->dropIndex(['vendor_stock', 'color']);
            $table->dropConstrainedForeignId('bqs_row_id');
            $table->dropColumn(['bqs_link_source', 'total_cartons_per_line']);
        });
    }

    /**
     * Fill the multiplier for orders already imported.
     *
     * The value is already in `purchase_orders.payload`, which is the advantage of
     * having kept the whole parse result — `documentation/merchandising.md` records
     * this exact recipe under "Promoting a payload field to a column". Matching is by
     * `pack_number`, the only identifier a line carries back to its pack.
     *
     * The JSON is decoded in PHP rather than queried with a JSON path, because the
     * path syntax differs between MySQL and SQLite and this has to run on both.
     */
    private function backfillCartonsPerLine(): void
    {
        DB::table('purchase_orders')->orderBy('id')->chunk(100, function ($orders): void {
            foreach ($orders as $order) {
                $payload = json_decode((string) $order->payload, true);

                if (! is_array($payload) || ! is_array($payload['packs'] ?? null)) {
                    continue;
                }

                foreach ($payload['packs'] as $pack) {
                    $cartons = $pack['line_item_header']['total_cartons_per_line'] ?? null;

                    if (! is_numeric($cartons) || ! isset($pack['pack_number'])) {
                        continue;
                    }

                    DB::table('po_line_items')
                        ->where('purchase_order_id', $order->id)
                        ->where('pack_number', $pack['pack_number'])
                        ->update(['total_cartons_per_line' => (int) $cartons]);
                }
            }
        });
    }
};
