<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders imported from a buyer's document.
 *
 * **The first buyer-owned table in the application** — the one ARCHITECTURE.md §9.2
 * was written for. `App\Models\Merchandising\PurchaseOrder` opts into `BuyerScoped`,
 * which requires the `buyer_id` column below.
 *
 * ## Why the header is columns and the rest is JSON
 *
 * A purchase-order document has roughly thirteen sections. Only the header is
 * filtered, sorted or joined on, and ARCHITECTURE.md §8.6 requires a list to filter
 * per *column* — `Listable::FILTERABLE` cannot address a JSON path portably across
 * MySQL and SQLite. So the header is promoted and the rest — addresses, logistics,
 * tariffs, comments, the packs — rides in `payload`. Line items are the exception and
 * get their own table, because Production computes consumption from them.
 *
 * ## Revisions
 *
 * Walmart reissues orders, and the document says so itself: `Revised Date … By:`.
 * That is the revision identity, not a counter this application invents.
 *
 * - `source_hash` makes re-importing the *same file* idempotent. It is unique per
 *   (buyer, PO number), so a duplicate upload is refused rather than becoming an
 *   identical "revision 2".
 * - `revision_no` orders the revisions that are genuinely different.
 * - `is_current` marks the newest, maintained inside the import transaction. It is
 *   derived from `max(revision_no)` and could be computed — a stored flag is kept
 *   because the list reads it on every request and a window function would be paid
 *   for on every row.
 *
 * `revised_at` alone could not carry any of this: it is nullable, and both MySQL and
 * SQLite permit repeated NULLs in a unique index — the same behaviour
 * `create_buyers_table` relies on for `code`.
 *
 * ## Indexing
 *
 * Per ARCHITECTURE.md §6.3: the two unique constraints **are** indexes and nothing
 * duplicates them. `buyer_id` leads both, and is what `BuyerScope` filters on, so the
 * scoped list seeks rather than scans. `buyer_id` and `po_import_id` are
 * `constrained()` and InnoDB indexes them anyway. `is_current` and `parse_status` are
 * deliberately **not** indexed: two and three values respectively is far too low a
 * cardinality to beat a scan, and both are applied as residual filters behind the
 * buyer predicate. Record any `EXPLAIN` that changes this in
 * `documentation/merchandising.md`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('po_import_id')->constrained('po_imports')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();

            /* Walmart's PO number is exactly ten digits, and it is an identifier. */
            $table->string('po_number', 10);
            $table->unsignedInteger('revision_no')->default(1);
            $table->dateTime('revised_at')->nullable();
            $table->string('revised_by', 50)->nullable();
            $table->string('source_hash', 64);
            $table->boolean('is_current')->default(true);

            /* The document's own status word (ACTIVE, CANCELLED …), not ours. */
            $table->string('document_status', 20)->nullable();
            $table->string('quote_id', 20)->nullable();
            $table->unsignedSmallInteger('po_type')->nullable();

            $table->date('create_date')->nullable();
            $table->date('negotiation_date')->nullable();
            $table->date('vendor_ship_date')->nullable();
            $table->date('cancel_date')->nullable();

            $table->string('currency', 3)->nullable();
            $table->decimal('exchange_rate', 12, 5)->nullable();

            $table->unsignedInteger('total_cartons')->nullable();
            $table->unsignedInteger('total_qty')->nullable();
            $table->decimal('total_weight_kgs', 12, 3)->nullable();
            $table->decimal('total_volume_cbm', 12, 3)->nullable();
            $table->decimal('net_first_cost_usd', 15, 4)->nullable();
            $table->decimal('net_first_cost_cnd', 15, 4)->nullable();

            /* Promoted so Production can find an order by who makes it. */
            $table->string('vendor_name', 150)->nullable();
            $table->string('factory_id', 30)->nullable();
            $table->string('factory_name', 150)->nullable();

            /*
             * The shape of the document this was read from. A fingerprint nobody has
             * seen before means Walmart changed the template — which is the signal
             * that the extractors are now quietly reading less than they used to.
             */
            $table->string('template_fingerprint', 12);

            $table->string('parse_status', 20);
            $table->decimal('confidence', 4, 3);

            /* Everything not promoted above: addresses, logistics, tariffs, packs. */
            $table->json('payload');

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'po_number', 'revision_no']);
            $table->unique(['buyer_id', 'po_number', 'source_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
