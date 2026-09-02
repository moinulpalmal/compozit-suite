<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How urgently a milestone reads, in the colours Settings already defines.
 *
 * ## Why the thresholds live here at all
 *
 * `notification_colors` holds a name, a hex and a retention period — and **nothing
 * that says which colour means "late"**. The four rows currently defined make that
 * concrete: their `retention_days` are `Urgent 5`, `Enough 15`, `Good 30`,
 * `Super Urgent 30`, which is not ordered by urgency and ties at the top. No existing
 * column can be read as a severity, so this feature has to declare the ladder itself.
 *
 * It is declared **per template** rather than once globally because urgency is
 * relative to the programme: on a 265-day buy a milestone three weeks out is
 * comfortable, and on an 80-day buy it is not.
 *
 * ## How a band is chosen
 *
 * `max_days_remaining` is the **inclusive upper bound** of the band, compared against
 * the days between today and the planned date. Bands are read in ascending order with
 * `null` last, and the first whose bound is at least the days remaining wins:
 *
 * ```
 *  -1   →  Super Urgent   the planned date has passed
 *   7   →  Urgent
 *  21   →  Enough
 * null  →  Good           the catch-all
 * ```
 *
 * The column is **signed** precisely so a negative bound can mean overdue, and
 * nullable so one band can mean "everything further out". A `smallInteger` is signed
 * on MySQL and SQLite alike.
 *
 * **At most one catch-all per template is enforced in the form request, not by a
 * unique index.** Repeated `NULL`s are permitted in a unique index on both drivers —
 * the same trap already documented on `bqs_sheets.root_id` — so an index here would
 * read as a guard while permitting exactly the thing it appears to forbid.
 *
 * ## This is the first foreign key into `notification_colors`
 *
 * That table shipped with no deletion guard, and both its migration and
 * documentation/settings.md §3.5 said so deliberately: nothing referenced it, and a
 * blocker that can only return `null` is dead code. This table ends that, so
 * `restrictOnDelete` is the database-level backstop and
 * `NotificationColorService::deletionBlocker()` is the message a person actually
 * sees. Deleting a colour a template paints with must fail loudly, not silently blank
 * a cell that someone reads as "on schedule".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tna_template_colors', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tna_template_id')->constrained('tna_templates')->cascadeOnDelete();

            /* See the docblock: the first reference into Settings' colour register. */
            $table->foreignId('notification_color_id')->constrained('notification_colors')->restrictOnDelete();

            /* Inclusive upper bound in days; signed for overdue, null for the catch-all. */
            $table->smallInteger('max_days_remaining')->nullable();

            $table->timestamps();

            /*
             * One band per colour per template — using a colour twice would make the
             * ladder ambiguous at the point it is read. The bounds themselves cannot
             * be made unique here, because the catch-all is `NULL` and unique indexes
             * permit repeated nulls on both drivers; the request checks those.
             *
             * This constraint is also the index the calculator loads bands by
             * (ARCHITECTURE.md §6.3), and InnoDB indexes `notification_color_id`
             * automatically as a foreign key.
             */
            $table->unique(['tna_template_id', 'notification_color_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tna_template_colors');
    }
};
