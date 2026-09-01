<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable schedule: given how long a programme runs, when each milestone is due.
 *
 * Settings-owned master data (ARCHITECTURE.md §9.4). Merchandising reads it to draw
 * the TNA page and never writes it, exactly like `notification_colors`.
 *
 * ## Why a range and not a lead time
 *
 * A template is matched by the programme's lead time — shipment date minus BQS date.
 * The obvious design keys the register on that number directly, and it was rejected
 * on evidence: the three purchase orders currently in the database run **263, 264 and
 * 265 days** against one BQS, because their ship dates are staggered by a day each.
 * Real lead times are arbitrary integers, so an exact key would need one row per
 * value and would match nothing the day a fourth order arrives on day 266.
 *
 * `lead_time_from` and `lead_time_to` are therefore a band, **inclusive at both
 * ends**, and one `241–300` row serves all three orders.
 *
 * ## Overlap is refused in the request, not here
 *
 * No portable constraint expresses "no two rows may overlap" — MySQL has no
 * exclusion constraint and SQLite has neither. `TnaTemplateStoreRequest` and its
 * update sibling check it, and only against **active** rows: retiring a band by
 * deactivating it is what lets a replacement cover the same days without a delete.
 * The matcher agrees, and only ever considers active templates.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tna_templates', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 100)->unique();

            /* Both ends inclusive: a 241–300 band contains 241 and 300. */
            $table->unsignedSmallInteger('lead_time_from');
            $table->unsignedSmallInteger('lead_time_to');

            $table->string('status', 1)->default(RecordStatus::Active->value);
            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /*
             * The only lookup this table has is "which band contains N", which reads
             * both columns together. `name` is covered by its unique constraint,
             * which already is an index (ARCHITECTURE.md §6.3), and `status` has two
             * values — too low in cardinality to be worth one.
             */
            $table->index(['lead_time_from', 'lead_time_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tna_templates');
    }
};
