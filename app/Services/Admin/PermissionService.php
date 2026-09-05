<?php

namespace App\Services\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\Admin\Permission;
use Illuminate\Support\Collection;

/**
 * Reads and writes the permission catalogue.
 *
 * Permission names are `{module}.{resource}.{action}` (ARCHITECTURE.md §9.1),
 * which is what lets the UI group them by module without a second table.
 */
class PermissionService
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Every permission name, grouped by module segment, for the pickers.
     *
     * @return array<string, list<array{id: int, name: string, resource: string, action: string}>>
     */
    public function groupedByModule(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission): string => $permission->module())
            ->map(fn (Collection $permissions): array => array_values(
                $permissions->map($this->describe(...))->all(),
            ))
            ->all();
    }

    /**
     * The distinct module segments, as the list's module filter renders them.
     *
     * Derived from the names rather than stored: the module *is* the first
     * dot-delimited segment, and a second table for a dozen values that are
     * already implied would be one more thing to keep in step.
     *
     * @return list<array{value: string, label: string}>
     */
    public function moduleOptions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): string => explode('.', $name)[0])
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $module): array => [
                'value' => $module,
                'label' => $module,
            ])
            ->all();
    }

    /**
     * Split a permission into the parts the picker renders.
     *
     * @return array{id: int, name: string, resource: string, action: string}
     */
    protected function describe(Permission $permission): array
    {
        [, $resource, $action] = array_pad(explode('.', $permission->name, 3), 3, '');

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'resource' => $resource,
            'action' => $action,
        ];
    }

    /**
     * Create a permission and attach it to the given roles.
     *
     * @param  list<string>  $roles
     */
    public function create(string $name, array $roles = []): void
    {
        /*
         * `query()->create()` rather than `Permission::create()`: spatie's static
         * is typed against its own contract, so the bare form hands back
         * `Spatie\...\Contracts\Permission` and loses this application's model.
         */
        $permission = Permission::query()->create(['name' => $name, 'guard_name' => 'web']);

        $this->syncRoles($permission, $roles);
    }

    /**
     * Rename a permission and re-sync the roles that hold it.
     *
     * @param  list<string>  $roles
     */
    public function update(Permission $permission, string $name, array $roles = []): void
    {
        $permission->update(['name' => $name]);

        $this->syncRoles($permission, $roles);
    }

    /**
     * Replace the roles holding a permission, and record the change.
     *
     * The same pivot as {@see RoleService::syncPermissions()} approached from the
     * other end — `role_has_permissions` again, written as raw rows with no model
     * event. Granting a permission to a role from this screen widens every account
     * holding that role, so it is recorded here for the same reason.
     *
     * @param  list<string>  $roles
     */
    private function syncRoles(Permission $permission, array $roles): void
    {
        $before = $this->roleNames($permission);

        $permission->syncRoles($roles);

        $this->audits->recordChange(
            $permission,
            AuditEvent::PermissionsChanged,
            ['roles' => $before],
            ['roles' => $this->roleNames($permission)],
        );
    }

    /**
     * The roles holding a permission, by name, in a stable order.
     *
     * @return list<string>
     */
    private function roleNames(Permission $permission): array
    {
        $names = $permission->roles()->pluck('name')->all();

        sort($names);

        return $names;
    }
}
