<?php

namespace App\Services\Admin;

use App\Models\Admin\Role;

/**
 * Creates, updates and deletes roles along with their permission set.
 */
class RoleService
{
    /**
     * Create a role and grant it the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function create(string $name, array $permissions = []): void
    {
        Role::create(['name' => $name, 'guard_name' => 'web'])
            ->syncPermissions($permissions);
    }

    /**
     * Rename a role and replace its permission set.
     *
     * @param  list<string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions = []): void
    {
        $role->update(['name' => $name]);

        $role->syncPermissions($permissions);
    }

    /**
     * Determine whether a role may be deleted.
     *
     * The super-admin role is permanent, and a role that still has users would
     * silently strip their access.
     */
    public function isDeletable(Role $role): bool
    {
        return ! $role->isSuperAdmin() && $role->users()->doesntExist();
    }
}
