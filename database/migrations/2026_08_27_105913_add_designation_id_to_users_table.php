<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every user one job title.
 *
 * The column is **nullable in the database but required in the form requests**.
 * The table already has rows, and backfilling them to an invented "Unassigned"
 * designation would put a row in the reference table that exists only to
 * satisfy a constraint. Existing users keep a null title and render as "—";
 * every user created or edited from now on must be given one, and the error
 * message for omitting it comes from the validator rather than the driver.
 *
 * `constrained()` creates the foreign key, and InnoDB indexes a foreign key
 * automatically — ARCHITECTURE.md §6.3 forbids adding a second index over it.
 * `nullOnDelete` is only a backstop: `DesignationService::deletionBlocker()`
 * refuses to delete a designation anybody holds.
 *
 * **No index was added for the users-list designation filter, and that was
 * measured.** `php artisan users:benchmark` was extended with the filter and a
 * `(deleted_at, designation_id, name)` candidate. The optimizer never chose it:
 * with the composite installed it still planned the rare-designation filter on
 * `users_designation_id_foreign + filesort` (1.58ms, versus 1.29–1.66ms across
 * every other run). The common designation — a third of the table — is
 * correctly scanned via `users_deleted_at_name_index` at ~1ms either way. An
 * index the planner declines to use is pure write cost. Figures in
 * documentation/admin.md §2.1.1; do not re-add it without new measurements.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('designation_id')
                ->nullable()
                ->after('gender')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('designation_id');
        });
    }
};
