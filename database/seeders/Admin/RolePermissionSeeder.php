<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the RBAC catalogue.
 *
 * Permission names follow `{module}.{resource}.{action}` (ARCHITECTURE.md §9.1).
 * The seeder is idempotent: it creates what is missing and re-syncs role
 * permissions, so it is safe to re-run after adding a module surface.
 */
class RolePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Every permission the application ships with, grouped by `{module}.{resource}`.
     *
     * @var array<string, list<string>>
     */
    protected const array CATALOGUE = [
        'admin.users' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'reset-password', 'assign-roles'],
        'admin.roles' => ['view', 'create', 'update', 'delete'],
        'admin.permissions' => ['view', 'create', 'update', 'delete'],
        'admin.buyers' => ['view', 'create', 'update', 'delete'],
        'admin.buyer-access' => ['view', 'update'],
        'admin.audit-logs' => ['view'],
        'settings.master-data' => ['view', 'create', 'update', 'delete'],
        'settings.application' => ['view', 'update'],
        'merchandising.tech-packs' => ['view', 'create', 'update', 'delete'],
        'merchandising.bqs' => ['view', 'create', 'update', 'delete'],
        'merchandising.purchase-orders' => ['view', 'create', 'update', 'delete'],
        'merchandising.bookings' => ['view', 'create', 'update', 'delete'],
        'production.orders' => ['view', 'create', 'update', 'delete'],
        'reports.merchandising' => ['view'],
        'reports.production' => ['view'],
    ];

    /**
     * The roles the application ships with, and the permission prefixes each holds.
     *
     * A prefix matches any permission that starts with it, so `merchandising.`
     * grants the whole module. `super-admin` is listed with no prefixes because
     * it is granted everything below.
     *
     * @var array<string, list<string>>
     */
    protected const array ROLES = [
        Role::SUPER_ADMIN => [],
        'admin' => ['admin.', 'settings.'],
        'merchandiser' => ['merchandising.', 'settings.master-data.view', 'reports.merchandising.'],
        'production-manager' => ['production.', 'merchandising.purchase-orders.view', 'reports.production.'],
        'viewer' => ['.view'],
    ];

    /**
     * Seed the roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->seedPermissions();

        foreach (self::ROLES as $name => $prefixes) {
            $role = Role::findOrCreate($name, 'web');

            $role->syncPermissions(
                $name === Role::SUPER_ADMIN
                    ? $permissions
                    : $permissions->filter(
                        fn (Permission $permission): bool => $this->matchesAny($permission->name, $prefixes),
                    ),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create every catalogued permission.
     *
     * Models are returned rather than names so the role sync below does not
     * have to resolve them through the permission cache.
     *
     * @return Collection<int, Permission>
     */
    protected function seedPermissions(): Collection
    {
        $permissions = new Collection;

        foreach (self::CATALOGUE as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions->push(Permission::findOrCreate("{$resource}.{$action}", 'web'));
            }
        }

        return $permissions;
    }

    /**
     * Determine whether a permission name matches any of the given prefixes.
     *
     * A prefix beginning with a dot matches the permission's suffix instead,
     * which is how the read-only `viewer` role picks up every `.view`.
     *
     * @param  list<string>  $prefixes
     */
    protected function matchesAny(string $permission, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $matched = str_starts_with($prefix, '.')
                ? str_ends_with($permission, $prefix)
                : str_starts_with($permission, $prefix);

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}
