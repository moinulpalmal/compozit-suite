<?php

namespace App\Concerns;

use App\Enums\RecordStatus;
use App\Models\Admin\Department;
use App\Services\Admin\BuyerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the department create and edit forms share.
 *
 * Extracted the way `DesignationValidationRules` is, so the two requests differ
 * only in the id they ignore for uniqueness.
 *
 * **`buyer_id` is checked against the actor's own buyer access**, not merely
 * against `buyers` — the same guarantee `Merchandising\BqsImportRequest`
 * documents. Creating a department under a buyer you cannot see would succeed and
 * `BuyerScope` would then hide the row on the redirect: a success message
 * followed by a list the row is missing from, which reads as a bug and is not
 * one. See ARCHITECTURE.md §9.2.
 *
 * **Uniqueness is scoped to the buyer, not global.** Two buyers both having a
 * `KIDSWEAR` is the normal case. Note `Rule::unique` builds a raw query and is
 * therefore *not* subject to `BuyerScope` — which is what this needs: a
 * collision must surface as a field error rather than as a driver exception from
 * the composite unique index behind it.
 */
trait DepartmentValidationRules
{
    /**
     * Get the validation rules that apply to a department.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function departmentRules(?int $departmentId = null): array
    {
        $buyerId = $this->input('buyer_id');

        return [
            'buyer_id' => [
                'required',
                'integer',
                Rule::in(array_keys(app(BuyerService::class)->assignableToActor())),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(Department::class, 'name')
                    ->where('buyer_id', $buyerId)
                    ->ignore($departmentId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(Department::class, 'code')
                    ->where('buyer_id', $buyerId)
                    ->ignore($departmentId),
            ],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * Messages that name the field the way the form labels it.
     *
     * The two `unique` messages say *that buyer* rather than "already exists",
     * because the same name under a different buyer is legal and a message that
     * did not say so would read as a bug.
     *
     * @return array<string, string>
     */
    protected function departmentMessages(): array
    {
        return [
            'buyer_id.required' => __('Choose which buyer this department belongs to.'),
            'buyer_id.in' => __('You do not have access to that buyer.'),
            'name.unique' => __('That buyer already has a department with that name.'),
            'code.unique' => __('That buyer already has a department with that code.'),
        ];
    }
}
