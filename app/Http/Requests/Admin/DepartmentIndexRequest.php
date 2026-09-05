<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Department;
use App\Services\Admin\BuyerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the department list's query string.
 */
class DepartmentIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return Department::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return Department::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * Both cells are dropdowns, so a value outside their allow-list is a
     * malformed request rather than a filter that finds nothing.
     *
     * The buyer cell is validated against the buyers the actor holds
     * **regardless of status**, which is why it uses
     * {@see BuyerService::filterOptionsForActor()} rather than
     * `assignableToActor()`: a retired buyer still has departments and an admin
     * has to be able to find them. That is the same split
     * `DesignationService::filterOptions()` makes against `assignableOptions()`.
     *
     * This is not the access control — `BuyerScope` already limits the rows to
     * the actor's buyers whatever this says. It is here so an out-of-range value
     * is a validation error rather than a silently empty list.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', Rule::enum(RecordStatus::class)],
            'filter.buyer_id' => [
                'nullable',
                'integer',
                Rule::in(array_keys(app(BuyerService::class)->filterOptionsForActor())),
            ],
        ];
    }
}
