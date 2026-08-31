<?php

namespace App\Models\Scopes;

use App\Concerns\BuyerScoped;
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
 */
class BuyerScope implements Scope
{
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

        /*
         * A user with no buyers sees nothing, which is a legitimate state for a
         * new hire pending assignment — the surfaces say so rather than showing
         * an empty table. See `components/shared/no-buyer-access.tsx`.
         */
        $builder->whereIn($model->qualifyColumn('buyer_id'), $actor->accessibleBuyerIds());
    }
}
