<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListRequest;
use App\Models\Admin\Role;

/**
 * Validates the role list's query string.
 *
 * Nothing beyond the shared sort / filter row / page rules — roles have no
 * filters of their own.
 */
class RoleIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return Role::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return Role::FILTERABLE;
    }
}
