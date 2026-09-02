<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A BQS — the buyer's buy plan for one product program, as one revision of it.
 *
 * Buyer-owned (ARCHITECTURE.md §9.2): `App\Models\Merchandising\BqsSheet` opts into
 * `BuyerScoped`, which requires the `buyer_id` column below.
 *
 * ## Why this is separate from `bqs_imports`
 *
 * An import is a *file*; a sheet is the *thing the file described*. An upload the
 * user answers with **skip** must still leave its file on the audit trail without
 * becoming a revision of anything, and that is only possible if the two are different
 * rows. It is the same file/entity/lines split the purchase-order module uses.
 *
 * ## Revisions, and how a collision is even detected
 *
 * A BQS workbook carries no document number, no revision date and — in every file seen
 * so far — no Quote ID. There is nothing to key a revision on, so the key is derived
 * from the rows instead:
 *
 * > **Two uploads are the same BQS when their sets of `bqs_rows.row_key` intersect.**
 *
 * That needs no invented identifier, produces no false collisions between unrelated
 * buys that happen to share a season and a fine line, and still asks the uploader one
 * question per workbook rather than one per row. An upload intersecting *two* current
 * sheets is refused: a workbook spanning two existing BQS revisions is a revision of
 * neither.
 *
 * ## `root_id` is the series, and it is why `title` is not the key
 *
 * Revisions chain through `root_id`, a self-reference that revision 1 points at
 * **itself** — written in a second statement inside the import transaction, because
 * the id does not exist until the insert returns.
 *
 * The obvious alternative, `unique(buyer_id, title, revision_no)`, is wrong: `title`
 * is the workbook's file name, and a reissued BQS routinely arrives under a different
 * one. Leaving `root_id` null on revision 1 instead is wrong for the reason
 * `create_purchase_orders_table` records — both MySQL and SQLite permit repeated
 * NULLs in a unique index, so the constraint would simply not bind the revision that
 * needs it most.
 *
 * `cascadeOnDelete` on `root_id` is safe rather than dangerous: **overwrite** only
 * ever deletes the *current* sheet, and the root is current only when it is the sole
 * revision — so the cascade fires exactly when deleting the series is what was meant.
 *
 * - `revision_no` orders the revisions.
 * - `is_current` marks the newest, maintained inside the import transaction. Derived
 *   from `max(revision_no)` and stored anyway, because the list reads it on every
 *   request and a window function would be paid for on every row.
 * - `source_hash` is the workbook's hash, carried here as well as on the import so a
 *   revision can be traced to its bytes without a join.
 *
 * ## Indexing
 *
 * Per ARCHITECTURE.md §6.3: the unique constraint **is** an index and nothing
 * duplicates it. `buyer_id`, `bqs_import_id` and `root_id` are `constrained()` and
 * InnoDB indexes them automatically — `buyer_id` is what `BuyerScope` filters on.
 * `is_current` and `parse_status` are deliberately **not** indexed: two and three
 * values are far too low a cardinality to beat a scan, and both are applied as
 * residual filters behind the buyer predicate.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bqs_sheets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('bqs_import_id')->constrained('bqs_imports')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();

            /* The first revision of this BQS; revision 1 points at itself. */
            $table->foreignId('root_id')->nullable()->constrained('bqs_sheets')->cascadeOnDelete();

            /*
             * The BQS date, carried from the import that produced this revision. It
             * is required, entered on the upload form, and never read from the
             * workbook — which carries no date at all. Each revision keeps its own.
             */
            $table->date('bqs_date');

            /*
             * Promoted from the rows because every row in a workbook carries the same
             * three, and the list filters and sorts on them. `fye` is a string: it is
             * the buyer's own label for a fiscal year, not arithmetic.
             */
            $table->string('fye', 10)->nullable();
            $table->string('season', 20)->nullable();
            $table->string('department', 100)->nullable();

            /* The workbook's own file name — the only human label a BQS has. */
            $table->string('title', 255);

            $table->unsignedInteger('revision_no')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('source_hash', 64);

            $table->unsignedInteger('row_count');
            $table->string('parse_status', 20);

            /* Warnings that belong to this revision rather than to the file. */
            $table->json('payload');

            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['root_id', 'revision_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bqs_sheets');
    }
};
