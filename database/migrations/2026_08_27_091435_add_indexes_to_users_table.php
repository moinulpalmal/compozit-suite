<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries the Admin user screens actually run.
 *
 * Deliberately *not* added, because they would be dead weight:
 *
 * - `employee_id` and `email` already carry unique indexes, which is what the
 *   login lookup and the availability check use.
 * - `inserted_by` / `last_updated_by` are foreign keys, and InnoDB creates an
 *   index for each automatically (`users_inserted_by_foreign` and friend).
 * - `name` and `email` are searched with a leading-wildcard `like '%term%'`,
 *   which no B-tree index can serve. That needs full-text search, not an index.
 * - `approved` is a two-value boolean, too low in cardinality to be selective.
 *
 * This was checked with `EXPLAIN`, not assumed: every query the Admin user screens
 * issue already resolves through an index, and the counts run off a covering index.
 * The plans, and the deferred `FULLTEXT` decision for search, are recorded in
 * `documentation/admin.md` §2.1.1–2.1.2. The general rule is ARCHITECTURE.md §6.3.
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
             * The user list is `where deleted_at is null order by name` on the
             * active tab and `is not null` on the historical one. A composite
             * over both columns serves the filter and the sort from one index.
             */
            $table->index(['deleted_at', 'name'], 'users_deleted_at_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_at_name_index');
        });
    }
};
