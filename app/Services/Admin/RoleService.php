<?php

namespace App\Services\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\Admin\Role;

/**
 * Creates, updates and deletes roles along with their permission set.
 */
class RoleService
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Create a role and grant it the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function create(string $name, array $permissions = []): void
    {
        /*
         * `query()->create()` rather than `Role::create()`: spatie's static is
         * typed against its own contract, so the bare form hands back
         * `Spatie\...\Contracts\Role` and loses this application's model.
         */
        $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);

        $this->syncPermissions($role, $permissions);
    }

    /**
     * Rename a role and replace its permission set.
     *
     * @param  list<string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions = []): void
    {
        $role->update(['name' => $name]);

        $this->syncPermissions($role, $permissions);
    }

    /**
     * Replace a role's permissions and record the change.
     *
     * `role_has_permissions` is a pivot: spatie writes it as raw rows and fires no
     * model event, so what a role can *do* could change with nothing in the trail
     * to show it — while the role's own `updated` audit records only its name.
     * Widening a role is how everybody holding it gains access at once, so it is
     * the change most worth being able to look up.
     *
     * See {@see UserService::syncRoles()} for why `permission.events_enabled`
     * stays off and this is diffed here instead.
     *
     * @param  list<string>  $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        $before = $this->permissionNames($role);

        $role->syncPermissions($permissions);

        $this->audits->recordChange(
            $role,
            AuditEvent::PermissionsChanged,
            ['permissions' => $before],
            ['permissions' => $this->permissionNames($role)],
        );
    }

    /**
     * A role's permissions by name, in a stable order.
     *
     * @return list<string>
     */
    private function permissionNames(Role $role): array
    {
        $names = $role->permissions()->pluck('name')->all();

        sort($names);

        return $names;
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
