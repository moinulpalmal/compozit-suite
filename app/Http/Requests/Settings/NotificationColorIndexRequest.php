<?php

namespace App\Http\Requests\Settings;

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Settings\NotificationColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the notification colour list's query string.
 *
 * Extends {@see ListRequest} rather than `FormRequest` (ARCHITECTURE.md §6.1),
 * so this surface inherits the whole paginate/sort/filter contract and adds
 * only the enum rule behind its status cell.
 */
class NotificationColorIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return NotificationColor::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return NotificationColor::FILTERABLE;
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
