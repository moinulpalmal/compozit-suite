<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces `users.approved` with `users.status`, on the shared `RecordStatus`.
 *
 * `designations.status` (`'A'`/`'I'`) and `users.approved` (boolean) said the
 * same thing two different ways. `App\Enums\RecordStatus` made the char form
 * the house vocabulary, so this is the table that moves —
 * **this reverses what documentation/admin.md §8.2 previously said**, which is
 * rewritten in the same change rather than left contradicting itself.
 *
 * `approval_authority` stays a boolean. It is a power flag, not an active flag:
 * a different concept that merely shares a fieldset.
 *
 * The index moves with the column. `users_deleted_at_approved_name_index` was
 * measured at 1.25ms → 0.25ms for the selective "inactive users, sorted by
 * name" case; `(deleted_at, status, name)` replaces it verbatim and was
 * re-measured. Figures in documentation/admin.md §2.1.1.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 1)
                ->default(RecordStatus::Active->value)
                ->after('designation_id');
        });

        DB::table('users')->where('approved', false)->update(['status' => RecordStatus::Inactive->value]);

        /*
         * The index has to go before the column it covers — SQLite rebuilds the
         * table on a drop, and would carry a dangling index definition into the
         * copy. Dropping it first is correct on both drivers.
         */
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_at_approved_name_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('approved');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['deleted_at', 'status', 'name'], 'users_deleted_at_status_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_at_status_name_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('approved')->default(true)->after('designation_id');
        });

        DB::table('users')
            ->where('status', RecordStatus::Inactive->value)
            ->update(['approved' => false]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['deleted_at', 'approved', 'name'], 'users_deleted_at_approved_name_index');
        });
    }
};
