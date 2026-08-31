<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Buyer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the buyer list's query string.
 *
 * Everything but the status rule comes from `ListRequest`; see
 * ARCHITECTURE.md §8.6.
 */
class BuyerIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return Buyer::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return Buyer::FILTERABLE;
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
