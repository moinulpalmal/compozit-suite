<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The application-wide audit trail — ARCHITECTURE.md §9.3.
 *
 * Written by `owen-it/laravel-auditing` through `App\Models\Admin\AuditLog`, and
 * named `audit_logs` rather than the package's `audits` because §5 and §6.3 both
 * committed to that name before the package existed here.
 *
 * Four columns depart from the package's published stub, each deliberately:
 *
 * 1. **`actor_name` / `actor_employee_id` are denormalised.** The package stores
 *    only `user_id`, and the reference implementation this was ported from
 *    resolves the name through the relation — which means a *soft-deleted* user's
 *    entire history renders as "System", because the default user provider applies
 *    the soft-delete scope. `users` is soft-deleted here and deleted accounts are
 *    kept on a Historical tab (§9.6), so that failure would be live on day one.
 *    Stamping the name at write time also gives the list its one
 *    `FilterType::Contains` column without a per-row subquery.
 * 2. **`auditable` is nullable.** Not every audited event is about a record: a
 *    failed login for an employee ID that matches no user has no subject to point
 *    at, and refusing to record it is the opposite of what an audit trail is for.
 * 3. **`old_values` / `new_values` are `longText`, not `text`.** `text` caps at
 *    64 KB on MySQL. `po_imports.payload` holds the parse result of a document
 *    carrying up to fifty purchase orders, so the stub's type would truncate —
 *    silently, on a non-strict connection. Those payload columns are excluded from
 *    auditing as well (`Concerns\Audited`), so this is the second of two guards
 *    rather than the only one.
 *
 * **There are no foreign keys, and that is not an oversight.** `user_id` is a bare
 * column so the trail outlives the account that made it — the one thing an audit
 * log must never lose is what a since-deleted user did. `auditable_id` is
 * polymorphic and cannot carry one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            /*
             * The actor, as the package writes it: a morph pair, so the resolver
             * could in principle name something other than a `User`.
             */
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            /* Denormalised — see the class docblock. */
            $table->string('actor_name')->nullable();
            $table->string('actor_employee_id', 10)->nullable();

            $table->string('event');

            /*
             * `nullableMorphs`, not `morphs`: an authentication event names no
             * record. It still creates the `(auditable_type, auditable_id)` index
             * the record-history query needs.
             */
            $table->nullableMorphs('auditable');

            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();

            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();

            /*
             * Comma-joined, and today only ever `buyer:{id}` — see
             * `Concerns\Audited::generateTags()`. Kept as the package's flat
             * string rather than normalised into a table, because the only reader
             * is one filter cell.
             */
            $table->string('tags')->nullable();

            $table->timestamps();

            /*
             * Indexing — ARCHITECTURE.md §6.3. Find the query first.
             *
             * - `(auditable_type, auditable_id)` comes free from `nullableMorphs()`
             *   above and serves the record-history query, the only one filtering on
             *   both. Its leading column also serves the model-type filter cell,
             *   which is an equality and therefore seeks.
             * - `(user_id, user_type)` serves the actor filter.
             * - **`event` is deliberately not indexed on its own.** Four values is
             *   not selective enough to beat a scan, and §6.3 forbids a bare
             *   low-cardinality index. `(event, created_at)` is the shape that earns
             *   its keep: an equality on `event` lets the index supply the
             *   `created_at` order, so a filtered page stops at the `LIMIT` instead
             *   of sorting the matches.
             * - `created_at` alone serves the default unfiltered view, which is how
             *   this screen is opened almost every time.
             * - `actor_name` gets **no** index. It is `FilterType::Contains`, and a
             *   leading wildcard cannot use a B-tree for the predicate at any
             *   selectivity; the `ORDER BY` is already served by `created_at`.
             */
            $table->index(['user_id', 'user_type']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
