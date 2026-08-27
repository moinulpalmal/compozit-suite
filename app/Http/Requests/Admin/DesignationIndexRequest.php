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
 * The shared sort / direction / search / page rules come from
 * {@see ListRequest}; the status filter is this surface's own.
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
    protected function searchable(): array
    {
        return Designation::SEARCHABLE;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return ['status' => $this->string('status')->value()];
    }
}
