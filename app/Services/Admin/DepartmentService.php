<?php

namespace App\Services\Admin;

use App\Models\Admin\Department;

/**
 * Creates, updates and deletes a buyer's merchandise departments.
 *
 * The deletion guard lives here rather than in a policy for the reason
 * `DesignationService` records: `AppServiceProvider::configureAuthorization()`
 * registers a `Gate::before` granting a super admin every ability, so a policy
 * denial would be bypassed for exactly the account most able to do damage. The
 * refusal is about the *record's* state, not the actor's power.
 *
 * There is no options/search endpoint here. A department is not offered by any
 * picker yet, and the buyer picker its own form needs is already served by
 * {@see BuyerService::assignableOptions()}, which is actor-scoped.
 */
class DepartmentService
{
    /**
     * Create a department.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Department
    {
        return Department::create($attributes);
    }

    /**
     * Update a department.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Department $department, array $attributes): void
    {
        $department->update($attributes);
    }

    /**
     * Delete a department.
     *
     * Call {@see self::deletionBlocker()} first — this does not check.
     */
    public function delete(Department $department): void
    {
        $department->delete();
    }

    /**
     * The reason this department may not be deleted, if there is one.
     *
     * **Nothing blocks yet, and that is correct today: nothing references a
     * department.** Merchandising carries its department as a free-text column on
     * `bqs_sheets` and `bqs_rows`, and `Merchandising\BqsRowKey::COMPONENTS`
     * hashes that string as component #3 — normalising it into a foreign key
     * would change every stored `row_key` and make every held BQS read as new, so
     * it waits for the BQS re-architecture.
     *
     * When that lands, the holder count goes here and the refusal points the
     * admin at deactivation instead. The seam exists now so that is one clause
     * rather than a change to the controller, the service and the tests at once —
     * the debt `NotificationColorService` shipped without it and had to repay the
     * moment `tna_template_colors` arrived (ARCHITECTURE.md §9.4).
     *
     * Deactivating (`status = 'I'`) is how a department stops appearing in
     * pickers while its rows stay readable. See ARCHITECTURE.md §9.3.1.
     */
    public function deletionBlocker(Department $department): ?string
    {
        return null;
    }
}
