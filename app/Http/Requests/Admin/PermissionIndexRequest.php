<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListRequest;
use App\Models\Admin\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the permission list's query string.
 *
 * The module filter replaces the grouped rendering this page used to do
 * client-side — grouping and pagination cannot both hold, because a group would
 * be cut across a page boundary. See documentation/admin.md §5.
 */
class PermissionIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return Permission::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function searchable(): array
    {
        return Permission::SEARCHABLE;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'module' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return ['module' => $this->string('module')->value()];
    }
}
