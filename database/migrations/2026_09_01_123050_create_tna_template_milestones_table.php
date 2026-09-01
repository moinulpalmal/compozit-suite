<?php

use App\Enums\Merchandising\TnaMilestone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One milestone of a template, and how many days after the BQS date it falls.
 *
 * ## Why rows rather than columns
 *
 * The proof of concept needs two offsets — trims approval and production sample
 * approval — which two columns on `tna_templates` would have carried. They are rows
 * because of where this is going: `Master Order recap.xls`, the sheet this feature
 * models, tracks roughly **twenty-five** milestone groups, each with a plan date, an
 * actual date and a status. A column per milestone means a migration every time the
 * business names a new one and a template table seventy-five columns wide; a row per
 * milestone means a new {@see TnaMilestone} case and nothing else.
 *
 * It costs nothing today. The form still renders exactly two number inputs, because
 * the inputs are driven by the enum either way.
 *
 * ## `Shipment` never appears here
 *
 * {@see TnaMilestone} has three cases but only two are offsets. Shipment is read from
 * `purchase_orders.vendor_ship_date` — it is the date the *buyer* set, and the one
 * lead time is measured to, so deriving it from an offset would let a template
 * silently contradict the order it describes. `TnaMilestone::offsetFromBqs()` states
 * the difference and the write requests enforce it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tna_template_milestones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tna_template_id')->constrained('tna_templates')->cascadeOnDelete();

            /* A {@see TnaMilestone} value. Wider than today's longest for headroom. */
            $table->string('milestone', 40);

            /* Days **after the BQS date**, which is where every offset is measured from. */
            $table->unsignedSmallInteger('offset_days');

            $table->timestamps();

            /*
             * One offset per milestone per template. This unique constraint is also
             * the index the calculator loads a template's milestones by, so no
             * separate index on `tna_template_id` is needed (ARCHITECTURE.md §6.3).
             */
            $table->unique(['tna_template_id', 'milestone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tna_template_milestones');
    }
};
