<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `In DC Units` band of a BQS row — one row per month, not one column per month.
 *
 * In the source workbook this band is eighteen columns headed `November-2026` through
 * `April-2028`. Those headers are **data**: the next season's file carries a different
 * range, and a column named `november_2026` would need an `ALTER TABLE` to accept it.
 * As rows, any range loads with no migration and a report can group by month.
 *
 * `month` is normalised to the first of the month so it sorts and joins; `month_label`
 * keeps the header verbatim, so a workbook can be rendered back in the buyer's own
 * wording without reformatting a date.
 *
 * **No `buyer_id`** — it reaches its buyer through `bqs_rows` → `bqs_sheets`, which is
 * scoped (ARCHITECTURE.md §9.2).
 *
 * **Indexing:** `unique(bqs_row_id, month)` **is** the index and its leading column is
 * the foreign key, so it serves the ordered read of one row's months as well as
 * enforcing that a month appears once. Nothing else is indexed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_row_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bqs_row_id')->constrained('bqs_rows')->cascadeOnDelete();

            $table->date('month');
            $table->string('month_label', 30);
            $table->unsignedInteger('dc_units')->nullable();

            $table->timestamps();

            $table->unique(['bqs_row_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_row_months');
    }
};
