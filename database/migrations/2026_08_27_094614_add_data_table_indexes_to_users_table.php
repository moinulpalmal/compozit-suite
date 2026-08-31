<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the Admin user list's sorting, filtering and targeted search.
 *
 * **Every index here was measured, not assumed.** `php artisan users:benchmark`
 * seeds 5,000 users and times each query pattern with and without each
 * candidate; only those that moved a number are below. Figures are recorded in
 * documentation/admin.md §2.1. The rule is ARCHITECTURE.md §6.3.
 *
 * Rejected candidates, and why — do not re-add without new measurements:
 *
 * - `(deleted_at, employee_id)` — sorting by employee ID was *slower* with it
 *   (0.42ms → 0.95ms). MySQL already scans `users_employee_id_unique` in order
 *   and stops at the page limit, which is optimal.
 * - `(deleted_at, email)` — same story via `users_email_unique`; the difference
 *   was inside the noise (0.56ms → 0.47ms).
 * - `(deleted_at, gender, name)` — gender has three values, too low in
 *   cardinality to be selective. Both plans were sub-millisecond.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /*
             * `order by created_at desc` — the only sort with nothing to supply
             * its order, so it was the one real filesort in the whole list.
             * 11.34ms -> 0.33ms.
             */
            $table->index(['deleted_at', 'created_at'], 'users_deleted_at_created_at_index');

            /*
             * Targeted prefix search on the directory columns. These pay off
             * only because search is field-scoped: an `OR` across every column
             * would make the optimizer ignore all of them.
             * Mobile 6.65ms -> 0.28ms, extension 6.65ms -> 0.19ms.
             */
            $table->index(['deleted_at', 'personal_mobile_no'], 'users_deleted_at_personal_mobile_index');
            $table->index(['deleted_at', 'official_mobile_no'], 'users_deleted_at_official_mobile_index');
            $table->index(['deleted_at', 'official_extension_no'], 'users_deleted_at_extension_index');

            /*
             * The status filter's selective case: few inactive users, sorted by
             * name and paginated, which otherwise scans a long way to fill one
             * page of 25. 1.25ms -> 0.25ms.
             */
            $table->index(['deleted_at', 'approved', 'name'], 'users_deleted_at_approved_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_at_created_at_index');
            $table->dropIndex('users_deleted_at_personal_mobile_index');
            $table->dropIndex('users_deleted_at_official_mobile_index');
            $table->dropIndex('users_deleted_at_extension_index');
            $table->dropIndex('users_deleted_at_approved_name_index');
        });
    }
};
