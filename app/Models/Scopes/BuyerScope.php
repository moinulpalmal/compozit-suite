<?php

namespace App\Models\Scopes;

use App\Concerns\BuyerScoped;
use App\Concerns\BuyerScopedOrGlobal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts a buyer-owned model to the buyers the signed-in user may see.
 *
 * The row-level half of ARCHITECTURE.md §9.2. A model opts in with
 * {@see BuyerScoped}; there is nothing else to register, which is
 * the point — an explicit `->visibleTo()` per service leaks across buyers the
 * first time somebody forgets it, and no test fails when they do.
 *
 * Escape it deliberately with `->withoutBuyerScope()`.
 *
 * **`$includeUnassigned` is for a model whose `buyer_id` is nullable**, and it exists
 * because the plain predicate below silently gets that case wrong: `NULL` never
 * matches an `IN` list, so a row belonging to no buyer would be invisible to every
 * user who is not a super admin — the opposite of what "no buyer" is meant to mean.
 * `document_uploads` is the first such table; it opts in through
 * {@see BuyerScopedOrGlobal} rather than {@see BuyerScoped}.
 */
class BuyerScope implements Scope
{
    /**
     * @param  bool  $includeUnassigned  Whether rows with a null `buyer_id` are visible
     *                                   to everyone rather than to nobody.
     */
    public function __construct(private bool $includeUnassigned = false) {}

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $actor = Auth::user();

        /*
         * No actor means system context — a seeder, a queue job, the scheduler,
         * an artisan command. Those run unfiltered on purpose: failing closed
         * here would make them silently do nothing, and every web path is behind
         * the `auth` middleware already. This is pinned by a test; it is a
         * decision, not an oversight.
         */
        if (! $actor instanceof User) {
            return;
        }

        if ($actor->seesAllBuyers()) {
            return;
        }

        $column = $model->qualifyColumn('buyer_id');

        /*
         * A user with no buyers sees nothing, which is a legitimate state for a
         * new hire pending assignment — the surfaces say so rather than showing
         * an empty table. See `components/shared/no-buyer-access.tsx`.
         */
        if (! $this->includeUnassigned) {
            $builder->whereIn($column, $actor->accessibleBuyerIds());

            return;
        }

        /*
         * **The closure is not optional.** `orWhere` binds looser than every other
         * `where` already on the query, so an ungrouped `->orWhereNull()` here would
         * read as `(… filters … AND buyer_id IN (…)) OR buyer_id IS NULL` — which is
         * every unassigned row in the table, unfiltered, on every filtered list. A
         * global scope cannot know what else the query holds, so it must never
         * contribute a top-level `OR`.
         */
        $builder->where(function (Builder $query) use ($column, $actor): void {
            $query->whereIn($column, $actor->accessibleBuyerIds())
                ->orWhereNull($column);
        });
    }
}
