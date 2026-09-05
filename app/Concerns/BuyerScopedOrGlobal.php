<?php

namespace App\Concerns;

use App\Models\Scopes\BuyerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marks a model as buyer-owned **with an optional buyer**: its rows are filtered by
 * the signed-in user's buyer access, and a row belonging to no buyer is visible to
 * everyone.
 *
 * The sibling of {@see BuyerScoped}, and the difference is one column being nullable:
 *
 * ```php
 * class DocumentUpload extends Model
 * {
 *     use BuyerScopedOrGlobal;   // buyer_id is nullable; null means "everyone"
 * }
 * ```
 *
 * **Do not reach for this to make a table easier to write.** It widens who can see a
 * row, which is the opposite of what ARCHITECTURE.md §9.2 exists to do, and it is
 * justified here by exactly one thing: a document that concerns no particular buyer —
 * a size chart, a TNA formula — has no buyer to be scoped to, and hiding it from
 * everyone would make the surface useless. A table whose rows always belong to a buyer
 * uses {@see BuyerScoped} and a non-nullable column.
 *
 * The trap this exists to close is that {@see BuyerScoped} does not merely *fail* to
 * cover the nullable case, it gets it silently backwards: `whereIn` never matches a
 * `NULL`, so unassigned rows would be visible to nobody but a super admin, and it
 * would read as a permissions bug rather than a modelling one.
 */
trait BuyerScopedOrGlobal
{
    /**
     * Boot the trait, registering the global scope.
     */
    public static function bootBuyerScopedOrGlobal(): void
    {
        static::addGlobalScope(new BuyerScope(includeUnassigned: true));
    }

    /**
     * Query across every buyer, ignoring the signed-in user's access.
     *
     * Named identically to {@see BuyerScoped::scopeWithoutBuyerScope()} so a call site
     * does not have to know which of the two traits a model uses to escape it.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithoutBuyerScope(Builder $query): void
    {
        $query->withoutGlobalScope(BuyerScope::class);
    }
}
