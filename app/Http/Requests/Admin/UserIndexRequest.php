<?php

namespace App\Http\Requests\Admin;

use App\Enums\Admin\Gender;
use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Designation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the Admin user list's query string.
 *
 * The shared sort / filter row / page rules come from {@see ListRequest}. What
 * this surface adds is the active-versus-historical tab, plus tighter rules for
 * three filter cells whose values are not free text.
 *
 * The tab is `?view=active|trashed`. It was `?filter=` until the filter row
 * arrived and claimed `filter[...]` — a scalar and an array cannot share a
 * query-string key. `view` is the better name for it regardless: it selects the
 * record set, not a column value, which is exactly why it is not in
 * `User::FILTERABLE`.
 */
class UserIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return User::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return User::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * The three cells below are dropdowns, so anything outside their option
     * list is a malformed request rather than a search that finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'view' => ['sometimes', 'in:active,trashed'],
            'filter.gender' => ['nullable', Rule::enum(Gender::class)],
            'filter.designation_id' => ['nullable', 'integer', Rule::exists(Designation::class, 'id')],
            'filter.status' => ['nullable', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return [
            'view' => $this->string('view')->value() === 'trashed' ? 'trashed' : 'active',
        ];
    }
}
