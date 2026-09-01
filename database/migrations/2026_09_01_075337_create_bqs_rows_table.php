<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a BQS workbook — a vendor style in one colourway.
 *
 * ## Why 61 columns and not the workbook's 89
 *
 * The source sheet has two header rows and 89 columns (A→CK). **28 of them are not
 * schema, they are data:** eighteen `In DC Units` columns headed with month names
 * (`November-2026 … April-2028`) and ten pack columns headed with size labels
 * (`XS(4/5) … XL(14/16)`). Both sets change with every season and every size range, so
 * a column per month would need an `ALTER TABLE` per upload. They live in
 * `bqs_row_months` and `bqs_row_pack_sizes` instead.
 *
 * Storing sizes as rows also keeps them joinable to `po_line_items`, which stores
 * colour and size as rows for exactly the same reason (ARCHITECTURE.md §5 — Production
 * computes consumption from quantity × colour × size).
 *
 * ## Naming
 *
 * The leaf header alone is ambiguous: `Store`, `Ecomm` and `OMNI` each appear **six**
 * times under six different row-1 bands. Every such column is therefore named
 * `{band}_{leaf}` — `initial_set_units_store`, `first_cost_ecomm`, and so on.
 *
 * One band and its leaf disagree in the source file: `AL1` reads *"Initial Set Units
 * Per Store"* while `AL2` reads *"Extra Initial Packs"*. The **leaf** wins, because it
 * is what the values are.
 *
 * ## Types
 *
 * Money is `decimal`, never float — `AV3` arrives from Excel as
 * `70711.199999999997`. Identifiers are strings even when they look numeric:
 * `colour_variant` (`503441`), `fine_line` (`5400`), `vendor_no` and `season_code` are
 * codes, not quantities, and leading zeros are significant. `pack_ratio` looks like a
 * ratio and is a **label** (`"FYE28 OPP Dress"`). `regular_imu_pct` is stored exactly
 * as the buyer sends it (`55`, not `0.55`).
 *
 * `wm_wk_in_store` is a composite the buyer jams into one cell — `"3 (2027-02-13)"` —
 * so the raw string is kept for fidelity and the two halves are parsed out beside it.
 *
 * ## The row key
 *
 * `row_key` is a sha256 of the seven normalised identity components chosen by the
 * owner: FYE, season, department, vendor style, pantone colour, colour variant and
 * item description. Those components are **also stored as ordinary columns**, so the
 * key is reproducible and debuggable rather than an opaque digest.
 *
 * It is what makes revision detection possible at all: `bqs_sheets` records that two
 * uploads are the same BQS when their row-key sets intersect.
 *
 * ## No `buyer_id`, and therefore no `BuyerScoped`
 *
 * ARCHITECTURE.md §9.2 is explicit that a model reaching its buyer through a parent
 * needs its own column rather than a scope that joins. Every read here goes through
 * `BqsSheet`, which is scoped, and the foreign key cascades — exactly as
 * `po_line_items` goes through `PurchaseOrder`.
 *
 * ## Indexing
 *
 * `bqs_sheet_id` is `constrained()` and InnoDB indexes it automatically. `row_key`
 * gets its own index because collision detection queries it directly on every upload —
 * that is a real query, not an imagined one. `(bqs_sheet_id, vendor_style_no)` is
 * indexed for the report that will match a BQS line to the purchase orders realising
 * it; no relationship is modelled yet, because the matching rule is not decided.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bqs_sheet_id')->constrained('bqs_sheets')->cascadeOnDelete();

            /* The sheet row this came from, so a warning can name it. */
            $table->unsignedInteger('line_no');
            $table->char('row_key', 64);

            /* --- A–AG: the 33 ungrouped leaf columns --- */
            $table->string('fye', 10)->nullable();
            $table->string('season', 20)->nullable();
            $table->string('department', 100)->nullable();

            /*
             * Column D is a *person* — "JELENA PAPAGEORGE", the buyer's own merchant.
             * It is never the `buyers` foreign key, which is on the parent sheet.
             */
            $table->string('buyer_merchant', 150)->nullable();

            $table->string('item_status', 50)->nullable();

            /* Blank in every file seen so far; kept because the column exists. */
            $table->string('quote_id', 50)->nullable();

            $table->string('category', 100)->nullable();
            $table->string('sub_category', 100)->nullable();
            $table->string('brand_id', 50)->nullable();
            $table->string('fine_line', 20)->nullable();
            $table->string('vendor_style_no', 50)->nullable();
            $table->string('item_description', 255)->nullable();
            $table->string('pantone_colour', 100)->nullable();
            $table->string('colour_family', 50)->nullable();
            $table->string('colour_variant', 50)->nullable();
            $table->string('other_colour', 100)->nullable();

            $table->decimal('first_cost', 12, 4)->nullable();
            $table->decimal('regular_cost', 12, 4)->nullable();
            $table->decimal('regular_retail', 12, 4)->nullable();
            $table->decimal('regular_imu_pct', 7, 3)->nullable();

            /* `"3 (2027-02-13)"` — kept raw, and split for use. */
            $table->string('wm_wk_in_store', 30)->nullable();
            $table->unsignedSmallInteger('wm_wk_in_store_week')->nullable();
            $table->date('wm_wk_in_store_date')->nullable();

            $table->decimal('reg_wos', 6, 2)->nullable();
            $table->string('season_code', 10)->nullable();

            /* A bare month name; its year comes from `fye` and the on-floor calendar. */
            $table->string('on_floor_month', 20)->nullable();

            $table->string('vendor_name', 150)->nullable();
            $table->string('vendor_no', 30)->nullable();
            $table->string('imp_dom', 5)->nullable();
            $table->string('country_of_origin', 100)->nullable();
            $table->string('factory_id', 30)->nullable();
            $table->string('factory_name', 150)->nullable();
            $table->string('initial_po_type', 50)->nullable();
            $table->string('replen_po_type', 50)->nullable();
            $table->decimal('reg_ecom_penetration_pct', 7, 3)->nullable();

            /* --- AH: Number of stores --- */
            $table->unsignedInteger('total_stores')->nullable();

            /* --- AI–AK: Initial Set Units --- */
            $table->unsignedInteger('initial_set_units_store')->nullable();
            $table->unsignedInteger('initial_set_units_ecomm')->nullable();
            $table->unsignedInteger('initial_set_units_omni')->nullable();

            /* --- AL: band says "Initial Set Units Per Store", leaf wins --- */
            $table->unsignedInteger('extra_initial_packs')->nullable();

            /* --- AM–AO: Total BUY Units --- */
            $table->unsignedInteger('total_buy_units_store')->nullable();
            $table->unsignedInteger('total_buy_units_ecomm')->nullable();
            $table->unsignedInteger('total_buy_units_omni')->nullable();

            /* --- AP–AR: Replenishment Units --- */
            $table->unsignedInteger('replenishment_units_store')->nullable();
            $table->unsignedInteger('replenishment_units_ecomm')->nullable();
            $table->unsignedInteger('replenishment_units_omni')->nullable();

            /* --- AS–AU: First Cost --- */
            $table->decimal('first_cost_store', 15, 4)->nullable();
            $table->decimal('first_cost_ecomm', 15, 4)->nullable();
            $table->decimal('first_cost_omni', 15, 4)->nullable();

            /* --- AV–AX: Landed Store Cost --- */
            $table->decimal('landed_store_cost_store', 15, 4)->nullable();
            $table->decimal('landed_store_cost_ecomm', 15, 4)->nullable();
            $table->decimal('landed_store_cost_omni', 15, 4)->nullable();

            /* --- AY–BA: Total Buy Dollar --- */
            $table->decimal('total_buy_dollar_store', 15, 4)->nullable();
            $table->decimal('total_buy_dollar_ecomm', 15, 4)->nullable();
            $table->decimal('total_buy_dollar_omni', 15, 4)->nullable();

            /* --- BB–BI: Pack Details --- */
            $table->string('commodity_type', 100)->nullable();
            $table->unsignedInteger('fixture_capacity')->nullable();
            $table->string('pack_ratio', 100)->nullable();
            $table->unsignedInteger('pack_units')->nullable();
            $table->string('replen_type', 50)->nullable();
            $table->string('replen_pack', 50)->nullable();
            $table->unsignedInteger('vndr_pack')->nullable();
            $table->unsignedInteger('whse_pack')->nullable();

            $table->timestamps();

            $table->unique(['bqs_sheet_id', 'row_key']);
            $table->index('row_key');
            $table->index(['bqs_sheet_id', 'vendor_style_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_rows');
    }
};
