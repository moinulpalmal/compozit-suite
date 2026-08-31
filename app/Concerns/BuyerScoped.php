<?php

namespace App\Concerns;

use App\Models\Scopes\BuyerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marks a model as buyer-owned: its rows are filtered by the signed-in user's
 * buyer access on every query.
 *
 * This `use` statement is the whole registration — see ARCHITECTURE.md §9.2:
 *
 * ```php
 * class PurchaseOrder extends Model
 * {
 *     use BuyerScoped;
 * }
 * ```
 *
 * It requires a `buyer_id` column on the model's own table. A model that reaches
 * its buyer through a parent needs its own `buyer_id` rather than a scope that
 * joins.
 *
 * The trait is preferred over `#[ScopedBy]` because it also supplies the named
 * escape hatch below: `withoutGlobalScope(BuyerScope::class)` is the same thing
 * spelled in a way that does not read, at the call site, like a deliberate
 * cross-buyer query.
 */
trait BuyerScoped
{
    /**
     * Boot the trait, registering the global scope.
     */
    public static function bootBuyerScoped(): void
    {
        static::addGlobalScope(new BuyerScope);
    }

    /**
     * Query across every buyer, ignoring the signed-in user's access.
     *
     * For reports and administrative paths that are *meant* to see everything.
     * Every call site is a deliberate exception and should say why.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithoutBuyerScope(Builder $query): void
    {
        $query->withoutGlobalScope(BuyerScope::class);
    }
}
