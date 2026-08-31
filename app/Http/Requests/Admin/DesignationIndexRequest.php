<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Designation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the designation list's query string.
 *
 * The status filter that used to sit in the toolbar is now a cell in
 * `Designation::FILTERABLE`; all this adds is the enum rule behind it.
 */
class DesignationIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return Designation::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return Designation::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * The status cell is a dropdown, so a value outside the enum is a malformed
     * request rather than a filter that finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', Rule::enum(RecordStatus::class)],
        ];
    }
}
