<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which buyers a user may see — the row-level half of ARCHITECTURE.md §9.2.
 *
 * A user with `users.all_buyer_access` has **no rows here at all**: the flag is
 * the grant, and `App\Models\Scopes\BuyerScope` short-circuits before it ever
 * reads this table. Materialising a wildcard into rows was considered and
 * rejected — it makes revocation lossy (a row granted by the wildcard becomes
 * indistinguishable from one granted deliberately) and needs a background job
 * whose failure is silently invisible. `Admin\BuyerAccessService::assign()`
 * clears these rows when the flag goes on, so the two can never disagree.
 *
 * Both foreign keys cascade: an access grant is a derived permission, not
 * history, so it dies with either end. Deleting a *buyer* is separately refused
 * while anything factual references it.
 *
 * No index beyond the unique pair. It serves the `whereIn` the scope runs
 * (leading `user_id`), and nothing lists users per buyer — the access dialog
 * lives on `admin/users` — so a reverse `(buyer_id, user_id)` index would be
 * pure write cost (ARCHITECTURE.md §6.3).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buyer_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();

            $table->unique(['user_id', 'buyer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_user');
    }
};
