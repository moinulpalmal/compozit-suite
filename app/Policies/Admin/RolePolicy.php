<?php

namespace App\Policies\Admin;

use App\Models\Admin\Role;
use App\Models\User;

/**
 * Record-level authorization for roles.
 *
 * Route-level gating is done by the `permission:` middleware in
 * `routes/admin.php`; this policy backs `Gate::authorize()` calls and the
 * `can` props the Admin pages render against.
 *
 * The super-admin role's immutability is deliberately *not* enforced here —
 * `Gate::before` grants a super admin every ability, so a policy denial would
 * be bypassed. That guard lives in `Admin\RoleController` instead.
 */
class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.roles.view');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('admin.roles.view');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.roles.create');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('admin.roles.update');
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('admin.roles.delete');
    }
}
