<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListRequest;
use App\Models\Admin\Permission;

/**
 * Validates the permission list's query string.
 *
 * The module filter replaces the grouped rendering this page used to do
 * client-side — grouping and pagination cannot both hold, because a group would
 * be cut across a page boundary. See documentation/admin.md §5. It is a cell in
 * the filter row like any other, declared as a scope because the module is a
 * segment of `name` rather than a column.
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
    protected function filterable(): array
    {
        return Permission::FILTERABLE;
    }
}
