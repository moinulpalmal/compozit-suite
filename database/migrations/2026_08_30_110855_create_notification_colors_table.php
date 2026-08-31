<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colours a notification can be raised in, and how long it is kept.
 *
 * The Settings module's first master-data table (ARCHITECTURE.md §9.4). Nothing
 * points at it yet — `notifications.notification_color_id` is the FK it exists
 * for, and is not built. That is why there is no deletion guard on the service;
 * see documentation/settings.md §3.5.
 *
 * `color_code` is a `char(7)` holding `#RRGGBB` **uppercase**. Normalisation
 * happens in the write requests' `prepareForValidation()` rather than in a
 * mutator, because the unique rule has to compare the normalised value — a
 * mutator runs after validation, so `#ff0000` and `#FF0000` would both pass the
 * unique check and then collide at the driver.
 *
 * `retention_days` is a duration, not an age: how many days a thing coloured
 * this way is kept. It was requested as `age`, which named a value rather than
 * a measurement; the column is named for what it measures.
 *
 * No index beyond the two unique constraints. A unique constraint already *is*
 * an index (ARCHITECTURE.md §6.3), so `name` and `color_code` are covered for
 * both lookup and `ORDER BY`; the table is bounded by however many colours a
 * business cares to define; `status` has two values, too low in cardinality to
 * be worth its own index; and InnoDB indexes the two foreign keys
 * automatically. `retention_days` is neither filtered nor sorted selectively.
 *
 * `char(7)` and `unsignedSmallInteger` are correct on MySQL and SQLite alike,
 * so this migration is safe under both development and test (§2).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_colors', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();

            /*
             * Unique because a colour is a visual signal: two rows sharing one
             * makes the signal ambiguous at a glance, which is the whole point
             * of the table. Refused at the database, not just in the form.
             */
            $table->char('color_code', 7)->unique();

            $table->unsignedSmallInteger('retention_days');
            $table->string('status', 1)->default(RecordStatus::Active->value);
            $table->foreignId('inserted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_colors');
    }
};
