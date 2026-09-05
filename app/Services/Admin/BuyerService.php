<?php

namespace App\Services\Admin;

use App\Models\Admin\Buyer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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
     * The buyers the signed-in user may import for: active, and within their access.
     *
     * Unpaginated by design — a picker and a list are different queries
     * (ARCHITECTURE.md §8.6), and a user's buyer set is short by construction.
     * `Buyer` is not itself buyer-scoped, so the access filter is applied here.
     *
     * **This lived on `PurchaseOrderImportService` while purchase orders were the only
     * importer.** The BQS import asks the identical question, and a second copy is how
     * two importers start disagreeing about who may upload for whom — so it moved here,
     * which this class's docblock already claimed as its job. `PurchaseOrderImportService`
     * keeps its own method name and delegates, so no caller of it changed.
     *
     * @return array<int, string> buyer id => name
     */
    public function assignableToActor(): array
    {
        $query = Buyer::query()->active();

        $actor = Auth::user();

        if ($actor !== null && ! $actor->seesAllBuyers()) {
            $query->whereIn('id', $actor->accessibleBuyerIds());
        }

        /** @var array<int, string> $buyers */
        $buyers = $query->orderBy('name')->pluck('name', 'id')->all();

        return $buyers;
    }

    /**
     * The buyers the signed-in user holds, **whatever their status**.
     *
     * The filter-cell counterpart to {@see self::assignableToActor()}, and the
     * split is deliberate: that method feeds *forms*, so it offers active buyers
     * only, while a filter has to be able to find the departments of a buyer that
     * has since been retired. `DesignationService::filterOptions()` makes the same
     * split against `assignableOptions()` for the same reason.
     *
     * This is not an access control. `BuyerScope` already limits buyer-owned rows
     * to the actor's buyers; this only decides what the dropdown offers and what
     * an out-of-range filter value is validated against.
     *
     * @return array<int, string> buyer id => name
     */
    public function filterOptionsForActor(): array
    {
        $query = Buyer::query();

        $actor = Auth::user();

        if ($actor !== null && ! $actor->seesAllBuyers()) {
            $query->whereIn('id', $actor->accessibleBuyerIds());
        }

        /** @var array<int, string> $buyers */
        $buyers = $query->orderBy('name')->pluck('name', 'id')->all();

        return $buyers;
    }

    /**
     * The same set, as a filter dropdown renders it.
     *
     * Named to match `DesignationService::filterOptions()`, which answers the
     * identical question for the users list.
     *
     * @return list<array{value: int, label: string}>
     */
    public function filterOptions(): array
    {
        $options = [];

        foreach ($this->filterOptionsForActor() as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name];
        }

        return $options;
    }

    /**
     * The same set, shaped for `components/ui/combobox.tsx`.
     *
     * @return list<array{value: int, label: string}>
     */
    public function assignableOptions(): array
    {
        $options = [];

        foreach ($this->assignableToActor() as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name];
        }

        return $options;
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
     * **Departments are the first fact that blocks**, and this line used to read
     * "nothing blocks yet". A department belongs to exactly one buyer, so
     * `departments.buyer_id` is `restrictOnDelete` and the database refuses the
     * delete on its own — this method exists so the admin is told *why* in a
     * sentence, rather than being shown the stack trace an integrity-constraint
     * violation produces (ARCHITECTURE.md §9.4).
     *
     * As the rest of Merchandising and Production land, every further table that
     * records a *fact* about a buyer — purchase orders, tech packs, bookings,
     * production output — is checked here too. Access grants are explicitly not
     * such a fact and cascade.
     *
     * The count deliberately escapes `BuyerScope`: this is a question about the
     * database, not about what the actor can see, and a department hidden from the
     * actor still blocks the delete just as hard.
     *
     * Deactivating (`status = 'I'`) is how a buyer stops appearing in pickers
     * while its history stays readable. See ARCHITECTURE.md §9.3.1.
     */
    public function deletionBlocker(Buyer $buyer): ?string
    {
        $departments = $buyer->departments()->withoutBuyerScope()->count();

        if ($departments === 0) {
            return null;
        }

        return trans_choice(
            '{1} One department belongs to this buyer. Deactivate the buyer instead, or delete that department first.'
            .'|[2,*] :count departments belong to this buyer. Deactivate the buyer instead, or delete them first.',
            $departments,
            ['count' => $departments],
        );
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
