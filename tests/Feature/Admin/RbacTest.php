<?php

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;
use Database\Seeders\Admin\RolePermissionSeeder;
use Illuminate\Foundation\Http\Kernel;

test('the models bound by spatie are the application models', function () {
    expect(config('permission.models.role'))->toBe(Role::class)
        ->and(config('permission.models.permission'))->toBe(Permission::class);
});

test('the seeder creates the catalogue and is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);

    $permissions = Permission::count();
    $roles = Role::count();

    expect($permissions)->toBeGreaterThan(0)
        ->and(Role::findByName(Role::SUPER_ADMIN))->not->toBeNull()
        ->and(Role::findByName('merchandiser')->hasPermissionTo('merchandising.tech-packs.update'))->toBeTrue()
        ->and(Role::findByName('merchandiser')->hasPermissionTo('admin.users.delete'))->toBeFalse();

    $this->seed(RolePermissionSeeder::class);

    expect(Permission::count())->toBe($permissions)
        ->and(Role::count())->toBe($roles);
});

test('every seeded permission name reads module.resource.action', function () {
    $this->seed(RolePermissionSeeder::class);

    Permission::pluck('name')->each(
        fn (string $name) => expect($name)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*){2}$/'),
    );
});

test('the viewer role is read-only', function () {
    $this->seed(RolePermissionSeeder::class);

    $viewer = Role::findByName('viewer');

    expect($viewer->permissions->pluck('name')->every(fn (string $name): bool => str_ends_with($name, '.view')))
        ->toBeTrue()
        ->and($viewer->permissions)->not->toBeEmpty();
});

test('a super admin passes every gate check', function () {
    $user = superAdmin();

    expect($user->can('merchandising.tech-packs.update'))->toBeTrue()
        ->and($user->can('anything.at.all'))->toBeTrue();
});

test('an ordinary user only passes the checks it was granted', function () {
    $user = userWithPermissions('merchandising.tech-packs.view');

    expect($user->can('merchandising.tech-packs.view'))->toBeTrue()
        ->and($user->can('merchandising.tech-packs.update'))->toBeFalse();
});

test('permissions are shared with every inertia page', function () {
    $this->actingAs(userWithPermissions('admin.roles.view'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', ['admin.roles.view']));
});

test('a super admin is shared as a wildcard rather than the whole catalogue', function () {
    $this->actingAs(superAdmin());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', ['*']));
});

test('guests are shared no permissions', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', []));
});

test('the permission middleware aliases are registered', function () {
    $aliases = app(Kernel::class)->getMiddlewareAliases();

    expect($aliases)->toHaveKeys(['role', 'permission', 'role_or_permission']);
});

test('users assigned a role inherit its permissions', function () {
    $role = Role::findOrCreate('merchandiser', 'web');
    $role->givePermissionTo(Permission::findOrCreate('merchandising.bqs.view', 'web'));

    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->can('merchandising.bqs.view'))->toBeTrue();
});
