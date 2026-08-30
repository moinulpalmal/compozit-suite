<?php

namespace App\Services\Admin;

use App\Models\Admin\Buyer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Creates, updates and deletes buyers, and decides which ones a picker may offer.
 *
 * The deletion guard lives here rather than in a policy for the reason
 * `UserService` records: `AppServiceProvider::configureAuthorization()` registers
 * a `Gate::before` granting a super admin every ability, so a policy denial would
 * be bypassed for exactly the account most able to do damage. This refusal is
 * about the *record's* state, not the actor's power.
 */
class BuyerService
{
    /**
     * Create a buyer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Buyer
    {
        return Buyer::create($attributes);
    }

    /**
     * Update a buyer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Buyer $buyer, array $attributes): void
    {
        $buyer->update($attributes);
    }

    /**
     * Delete a buyer.
     *
     * Its `buyer_user` rows go with it — the foreign key cascades. That is
     * deliberate: an access grant is a derived permission, not history, and
     * requiring an admin to strip thirty users before removing a mistyped buyer
     * would be a guard nobody thanks you for. Facts are the opposite case; see
     * {@see self::deletionBlocker()}.
     *
     * Call that first — this does not check.
     */
    public function delete(Buyer $buyer): void
    {
        $buyer->delete();
    }

    /**
     * The reason this buyer may not be deleted, if there is one.
     *
     * **Nothing blocks yet, and that is correct today:** no buyer-owned table
     * exists. As Merchandising and Production land, every table that records a
     * *fact* about a buyer — purchase orders, tech packs, bookings, production
     * output — is checked here, and the refusal points the admin at deactivation
     * instead. Access grants are explicitly not such a fact.
     *
     * Deactivating (`status = 'I'`) is how a buyer stops appearing in pickers
     * while its history stays readable. See ARCHITECTURE.md §9.3.1.
     */
    public function deletionBlocker(Buyer $buyer): ?string
    {
        return null;
    }

    /**
     * Buyers matching a typed search term, for a combobox.
     *
     * Backs `admin.buyers.options`. Capped, because not shipping the table to
     * the browser is the entire point, and matched as a **prefix** so the unique
     * index on `code` stays seekable (ARCHITECTURE.md §6.3 and §8.5). Inactive
     * buyers are excluded: this feeds the access picker, and granting access to
     * a retired buyer is not something to offer.
     *
     * @return list<array{value: int, label: string, hint: string|null}>
     */
    public function searchAssignable(?string $term, int $limit = 50): array
    {
        $term = trim((string) $term);

        return Buyer::query()
            ->active()
            ->when($term !== '', function (Builder $query) use ($term): void {
                // Otherwise a term of "%" becomes a wildcard over the table.
                $escaped = addcslashes($term, '%_\\');

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "{$escaped}%")
                    ->orWhere('code', 'like', "{$escaped}%"));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'code'])
            ->map(fn (Buyer $buyer): array => [
                'value' => $buyer->id,
                'label' => $buyer->name,
                'hint' => $buyer->code,
            ])
            ->all();
    }

    /**
     * The buyers a user already holds, as the access dialog renders them.
     *
     * Deliberately **unpaginated and unfiltered by status**: these are grants
     * that exist, and a retired buyer somebody still holds has to be visible so
     * it can be removed. A list and its picker are different queries
     * (ARCHITECTURE.md §8.6).
     *
     * @param  Collection<int, Buyer>  $buyers
     * @return list<array{value: int, label: string, hint: string|null}>
     */
    public function describeHeld(Collection $buyers): array
    {
        return $buyers
            ->map(fn (Buyer $buyer): array => [
                'value' => $buyer->id,
                'label' => $buyer->name,
                'hint' => $buyer->code,
            ])
            ->values()
            ->all();
    }
}
