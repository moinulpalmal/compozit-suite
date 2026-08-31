<?php

namespace App\Policies\Admin;

use App\Models\Admin\Permission;
use App\Models\User;

/**
 * Record-level authorization for permissions.
 *
 * @see RolePolicy for how this pairs with route-level gating.
 */
class PermissionPolicy
{
    /**
     * Determine whether the user can view any permissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.permissions.view');
    }

    /**
     * Determine whether the user can view the permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->can('admin.permissions.view');
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.permissions.create');
    }

    /**
     * Determine whether the user can update the permission.
     */
    public function update(User $user, Permission $permission): bool
    {
        return $user->can('admin.permissions.update');
    }

    /**
     * Determine whether the user can delete the permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('admin.permissions.delete');
    }
}
