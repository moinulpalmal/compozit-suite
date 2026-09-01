<?php

use App\Services\Merchandising\BqsRowKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's decision about which BQS row a purchase-order colour means.
 *
 * ## Why this table exists at all
 *
 * The owner chose **strict equality** for colour matching: a PO colour auto-links only
 * when its family and its Pantone name both equal the BQS row's exactly. Walmart
 * truncates that field to fifteen characters, so on the reference documents
 * `LTBLUE-BALLAD B` (from `BALLAD BLUE`) and `NATURL-SANDSHEL` (from `SANDSHELL`) can
 * never match, and only `PINK-CANDY PINK` does. Roughly half of every future order
 * therefore needs a human decision.
 *
 * **If that decision were a fact about one line item, it would be re-made on every
 * subsequent purchase order, forever.** So it is stored as a *rule* instead: this
 * table maps a colour to a BQS row for a buyer and a style, and every later order
 * carrying that colour resolves through it with no further input. It is what makes
 * strict equality survivable rather than exhausting.
 *
 * ## Keyed on `row_key`, not on a row id
 *
 * `bqs_row_key` is {@see BqsRowKey}'s hash — the identity
 * that stays the same across BQS revisions, which is exactly what it was built for. A
 * foreign key to `bqs_rows.id` would be orphaned the first time the buyer reissued the
 * sheet, which is the routine case rather than the exceptional one.
 *
 * It is deliberately **not** a foreign key: the row it names may not exist yet. A
 * colour can be mapped before the BQS revision that will carry it has been imported,
 * and the mapping simply resolves to nothing until it does.
 *
 * ## Indexing
 *
 * `unique(buyer_id, vendor_style_no, po_color)` **is** the index and is exactly how the
 * linker looks a mapping up, buyer-first. Nothing else is indexed — this table is
 * reached by that key or not at all.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_colour_links', function (Blueprint $table): void {
            $table->id();

            /*
             * The buyer scopes the rule. Neither `po_line_items` nor `bqs_rows`
             * carries a `buyer_id` — both reach it through a parent (ARCHITECTURE.md
             * §9.2) — so this column is what keeps a mapping from ever being applied
             * across buyers.
             */
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();

            $table->string('vendor_style_no', 50);

            /* The PO's colour verbatim, truncation and all: `LTBLUE-BALLAD B`. */
            $table->string('po_color', 100);

            /* The BQS row's stable identity — see the docblock on why not an id. */
            $table->char('bqs_row_key', 64);

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'vendor_style_no', 'po_color']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_colour_links');
    }
};
