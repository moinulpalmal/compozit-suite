<?php

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.permissions.index'))->assertRedirect(route('login'));
});

test('users without the view permission are denied', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.permissions.index'))->assertForbidden();
});

test('permissions are listed grouped by module', function () {
    $this->actingAs(userWithPermissions('admin.permissions.view'));

    Permission::findOrCreate('merchandising.tech-packs.view', 'web');

    $this->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/permissions/index')
            ->where('permissions.0.name', 'admin.permissions.view')
            ->where('permissions.0.module', 'admin')
            ->where('permissions.1.name', 'merchandising.tech-packs.view')
            ->where('permissions.1.module', 'merchandising'));
});

test('a permission is created and attached to roles', function () {
    $this->actingAs(userWithPermissions('admin.permissions.create'));

    Role::findOrCreate('merchandiser', 'web');

    $this->post(route('admin.permissions.store'), [
        'name' => 'merchandising.bookings.create',
        'roles' => ['merchandiser'],
    ])->assertRedirect(route('admin.permissions.index'));

    expect(Role::findByName('merchandiser')->hasPermissionTo('merchandising.bookings.create'))
        ->toBeTrue();
});

test('permission names must read module.resource.action', function (string $name) {
    $this->actingAs(userWithPermissions('admin.permissions.create'));

    $this->post(route('admin.permissions.store'), ['name' => $name])
        ->assertSessionHasErrors('name');

    expect(Permission::whereName($name)->exists())->toBeFalse();
})->with([
    'too few segments' => 'merchandising.update',
    'too many segments' => 'merchandising.tech-packs.lines.update',
    'not kebab-case' => 'Merchandising.TechPacks.update',
    'trailing dot' => 'merchandising.tech-packs.',
]);

test('the create form omits the super-admin role', function () {
    $this->actingAs(userWithPermissions('admin.permissions.create'));

    Role::findOrCreate(Role::SUPER_ADMIN, 'web');
    Role::findOrCreate('merchandiser', 'web');

    $this->get(route('admin.permissions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/permissions/create')
            ->where('roles', ['merchandiser']));
});

test('a permission is renamed and its roles re-synced', function () {
    $this->actingAs(userWithPermissions('admin.permissions.update'));

    $permission = Permission::findOrCreate('merchandising.bqs.veiw', 'web');
    Role::findOrCreate('merchandiser', 'web')->givePermissionTo($permission);

    $this->put(route('admin.permissions.update', $permission), [
        'name' => 'merchandising.bqs.view',
        'roles' => [],
    ])->assertRedirect(route('admin.permissions.index'));

    expect($permission->refresh()->name)->toBe('merchandising.bqs.view')
        ->and(Role::findByName('merchandiser')->permissions)->toBeEmpty();
});

test('a permission is deleted', function () {
    $this->actingAs(userWithPermissions('admin.permissions.delete'));

    $permission = Permission::findOrCreate('merchandising.bqs.view', 'web');

    $this->delete(route('admin.permissions.destroy', $permission))
        ->assertRedirect(route('admin.permissions.index'));

    expect(Permission::whereName('merchandising.bqs.view')->exists())->toBeFalse();
});

test('duplicate permission names are rejected', function () {
    $this->actingAs(userWithPermissions('admin.permissions.create'));

    Permission::findOrCreate('merchandising.bqs.view', 'web');

    $this->post(route('admin.permissions.store'), ['name' => 'merchandising.bqs.view'])
        ->assertSessionHasErrors('name');
});
