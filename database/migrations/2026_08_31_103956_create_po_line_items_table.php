<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colour/size lines of an imported purchase order.
 *
 * **The one part of a purchase-order document that becomes rows rather than JSON.**
 * ARCHITECTURE.md §5 makes Merchandising the upstream source of consumption data that
 * Production reads, and consumption is computed from quantity × colour × size. Those
 * three live here, so they have to be joinable rather than buried in a payload.
 *
 * Pack identity is denormalised onto each line (`pack_number`, `pack_description`,
 * `assortment_id`, `vendor_stock`) rather than given a `po_packs` table of its own. A
 * pack carries no fact beyond its identifiers and its cost stack, and the cost stack
 * is not queried — so a third table would exist only to be joined through. The packs
 * are still in the order's `payload` in full.
 *
 * **No `buyer_id`, and therefore no `BuyerScoped`.** ARCHITECTURE.md §9.2 is explicit
 * that a model reaching its buyer through a parent needs its own column rather than a
 * scope that joins. Every read here goes through `PurchaseOrder`, which is scoped, and
 * the foreign key cascades — so a line item is never reachable without its order.
 *
 * **Indexing:** `purchase_order_id` is `constrained()` and InnoDB indexes it
 * automatically (ARCHITECTURE.md §6.3). Nothing else is indexed because nothing
 * filters on it yet; the first report that groups by colour or size adds the index its
 * own `EXPLAIN` calls for, and records the reasoning in `documentation/merchandising.md`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('po_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            /* Which pack this line belongs to, denormalised — see the docblock. */
            $table->unsignedInteger('pack_number')->nullable();
            $table->string('pack_description', 255)->nullable();
            $table->string('assortment_id', 30)->nullable();
            $table->string('vendor_stock', 30)->nullable();

            $table->string('color', 100)->nullable();
            $table->string('size', 50)->nullable();
            $table->unsignedInteger('quantity')->nullable();

            /*
             * Identifiers, all strings: a UPC is not a quantity, and its leading
             * zeros are significant.
             */
            $table->string('item_number', 20)->nullable();
            $table->string('vendor_stock_number', 30)->nullable();
            $table->string('mfg_stock_number', 30)->nullable();
            $table->string('product_number', 20)->nullable();
            $table->string('upc_number', 20)->nullable();

            $table->string('item_description1', 255)->nullable();
            $table->string('item_description2', 255)->nullable();
            $table->string('upc_description', 255)->nullable();
            $table->string('signing_description', 255)->nullable();

            $table->decimal('uom_qty', 12, 3)->nullable();
            $table->string('uom_code', 10)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_line_items');
    }
};
