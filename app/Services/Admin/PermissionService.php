<?php

namespace App\Services\Admin;

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
        Permission::create(['name' => $name, 'guard_name' => 'web'])
            ->syncRoles($roles);
    }

    /**
     * Rename a permission and re-sync the roles that hold it.
     *
     * @param  list<string>  $roles
     */
    public function update(Permission $permission, string $name, array $roles = []): void
    {
        $permission->update(['name' => $name]);

        $permission->syncRoles($roles);
    }
}
