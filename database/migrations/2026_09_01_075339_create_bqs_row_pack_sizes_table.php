<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `Break Packs` and `Case Packs` bands of a BQS row — one row per size.
 *
 * In the source workbook these are ten columns headed `XS(4/5) … XL(14/16)` twice
 * over. Those headers are **data**: this file is girls' dresses, a men's shirt BQS
 * carries S/M/L/XL/XXL and a trouser BQS carries waist sizes. As rows, any size set
 * loads with no migration.
 *
 * It is also what keeps a BQS joinable to `po_line_items`, which stores colour and
 * size as rows for the same reason (ARCHITECTURE.md §5).
 *
 * `size_order` preserves the buyer's own column sequence, so the sheet renders
 * XS → S → M → L → XL rather than alphabetically. A size label is text and sorts
 * meaninglessly; the order the buyer wrote it in is the only correct one.
 *
 * **No `buyer_id`** — it reaches its buyer through `bqs_rows` → `bqs_sheets`, which is
 * scoped (ARCHITECTURE.md §9.2).
 *
 * **Indexing:** `unique(bqs_row_id, pack_type, size_label)` **is** the index and its
 * leading column is the foreign key, so it serves reading one row's packs as well as
 * enforcing that a size appears once per band.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_row_pack_sizes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bqs_row_id')->constrained('bqs_rows')->cascadeOnDelete();

            /* `App\Enums\Merchandising\BqsPackType` — 'break' or 'case'. */
            $table->string('pack_type', 10);

            $table->string('size_label', 30);
            $table->unsignedTinyInteger('size_order');
            $table->unsignedInteger('quantity')->nullable();

            $table->timestamps();

            $table->unique(['bqs_row_id', 'pack_type', 'size_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_row_pack_sizes');
    }
};
