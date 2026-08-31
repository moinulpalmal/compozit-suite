<?php

namespace App\Services\Admin;

use App\Models\Admin\Designation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Creates, updates and deletes designations, and decides which ones a user
 * form may offer.
 *
 * The deletion guard lives here rather than in a policy for the reason
 * `UserService` records: `AppServiceProvider::configureAuthorization()`
 * registers a `Gate::before` granting a super admin every ability, so a policy
 * denial would be bypassed for exactly the account most able to do damage.
 * This refusal is about the *record's* state, not the actor's power.
 */
class DesignationService
{
    /**
     * Create a designation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Designation
    {
        return Designation::create($attributes);
    }

    /**
     * Update a designation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Designation $designation, array $attributes): void
    {
        $designation->update($attributes);
    }

    /**
     * Delete a designation.
     *
     * Call {@see self::deletionBlocker()} first — this does not check.
     */
    public function delete(Designation $designation): void
    {
        $designation->delete();
    }

    /**
     * The reason this designation may not be deleted, if there is one.
     *
     * Soft-deleted users count. They still hold the title on the Historical
     * tab, and restoring one whose designation had been deleted underneath it
     * would silently blank the field.
     *
     * Deactivating is the way to retire a designation somebody holds; deleting
     * is for rows that were never used, or are no longer referenced at all.
     */
    public function deletionBlocker(Designation $designation): ?string
    {
        $holders = $this->holderCount($designation);

        if ($holders === 0) {
            return null;
        }

        return trans_choice(
            '{1} One user holds this designation. Deactivate it instead, or move that user to another designation.'
            .'|[2,*] :count users hold this designation. Deactivate it instead, or move them to another designation.',
            $holders,
            ['count' => $holders],
        );
    }

    /**
     * How many users — including soft-deleted ones — hold this designation.
     */
    public function holderCount(Designation $designation): int
    {
        return $designation->users()->withTrashed()->count();
    }

    /**
     * The designations a user form may offer.
     *
     * Active ones, **plus any id in `$keep` even if it has been deactivated**.
     * The user list passes the designations its current page already holds, so
     * opening the edit modal for somebody with a retired title shows that
     * title rather than an empty select — which would otherwise blank the
     * field on save, or fail validation on a value the admin never touched.
     * `EmployeeValidationRules::designationRules()` grants the same exception
     * per user, and the two have to agree.
     *
     * @param  list<int>  $keep
     * @return list<array{value: int, label: string, short_form: string|null, status: string}>
     */
    public function assignableOptions(array $keep = []): array
    {
        $keep = array_values(array_unique(array_filter($keep)));

        return Designation::query()
            ->where(fn (Builder $query) => $query
                ->active()
                ->when($keep !== [], fn (Builder $inner) => $inner->orWhereIn('id', $keep)))
            ->orderBy('name')
            ->get()
            ->map(fn (Designation $designation): array => [
                'value' => $designation->id,
                'label' => $designation->name,
                'short_form' => $designation->short_form,
                'status' => $designation->status->value,
            ])
            ->all();
    }

    /**
     * Assignable designations matching a typed search term.
     *
     * Backs `admin.designations.options`, the combobox's async source. Capped,
     * because the whole point is not shipping the table to the browser; the cap
     * is generous enough that a term narrow enough to be useful is never
     * truncated. Matching is a prefix on name or short form — the same
     * indexable shape `User::scopeSearch()` uses, for the same reason
     * (ARCHITECTURE.md §6.3).
     *
     * @return list<array{value: int, label: string, hint: string|null}>
     */
    public function searchAssignable(?string $term, int $limit = 50): array
    {
        $term = trim((string) $term);

        return Designation::query()
            ->active()
            ->when($term !== '', function (Builder $query) use ($term): void {
                // Otherwise a term of "%" becomes a wildcard over the table.
                $escaped = addcslashes($term, '%_\\');

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "{$escaped}%")
                    ->orWhere('short_form', 'like', "{$escaped}%"));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'short_form'])
            ->map(fn (Designation $designation): array => [
                'value' => $designation->id,
                'label' => $designation->name,
                'hint' => $designation->short_form,
            ])
            ->all();
    }

    /**
     * Every designation, as the users-list filter dropdown renders it.
     *
     * Inactive ones are included here on purpose: a retired title still has
     * holders, and an admin has to be able to find them.
     *
     * @return list<array{value: int, label: string}>
     */
    public function filterOptions(): array
    {
        return Designation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Designation $designation): array => [
                'value' => $designation->id,
                'label' => $designation->name,
            ])
            ->all();
    }
}
