<?php

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
});

test('users without the view permission are denied', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.roles.index'))->assertForbidden();
});

test('users with the view permission see the role list', function () {
    $this->actingAs(userWithPermissions('admin.roles.view'));

    Role::findOrCreate('merchandiser', 'web');

    $this->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/index')
            ->has('roles.data', 1)
            ->where('roles.data.0.name', 'merchandiser'));
});

test('a super admin reaches the role list without an explicit permission', function () {
    $this->actingAs(superAdmin());

    $this->get(route('admin.roles.index'))->assertOk();
});

test('a role is created with its permissions', function () {
    $this->actingAs(userWithPermissions('admin.roles.create'));

    Permission::findOrCreate('merchandising.tech-packs.view', 'web');

    $this->post(route('admin.roles.store'), [
        'name' => 'merchandiser',
        'permissions' => ['merchandising.tech-packs.view'],
    ])->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('merchandiser');

    expect($role->hasPermissionTo('merchandising.tech-packs.view'))->toBeTrue();
});

test('role names must be kebab-case', function () {
    $this->actingAs(userWithPermissions('admin.roles.create'));

    $this->post(route('admin.roles.store'), ['name' => 'Production Manager'])
        ->assertSessionHasErrors('name');

    expect(Role::count())->toBe(0);
});

test('a role is updated and its permissions replaced', function () {
    $this->actingAs(userWithPermissions('admin.roles.update'));

    $role = Role::findOrCreate('merchandiser', 'web');
    $role->givePermissionTo(Permission::findOrCreate('merchandising.bqs.view', 'web'));
    Permission::findOrCreate('merchandising.bqs.update', 'web');

    $this->put(route('admin.roles.update', $role), [
        'name' => 'senior-merchandiser',
        'permissions' => ['merchandising.bqs.update'],
    ])->assertRedirect(route('admin.roles.index'));

    $role->refresh();

    expect($role->name)->toBe('senior-merchandiser')
        ->and($role->hasPermissionTo('merchandising.bqs.update'))->toBeTrue()
        ->and($role->hasPermissionTo('merchandising.bqs.view'))->toBeFalse();
});

test('the super-admin role cannot be modified', function () {
    $this->actingAs(userWithPermissions('admin.roles.update'));

    $role = Role::findOrCreate(Role::SUPER_ADMIN, 'web');

    $this->put(route('admin.roles.update', $role), ['name' => 'root'])
        ->assertForbidden();

    expect($role->refresh()->name)->toBe(Role::SUPER_ADMIN);
});

test('an unused role is deleted', function () {
    $this->actingAs(userWithPermissions('admin.roles.delete'));

    $role = Role::findOrCreate('merchandiser', 'web');

    $this->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::whereName('merchandiser')->exists())->toBeFalse();
});

test('a role still assigned to a user is kept', function () {
    $this->actingAs(userWithPermissions('admin.roles.delete'));

    $role = Role::findOrCreate('merchandiser', 'web');
    User::factory()->create()->assignRole($role);

    $this->from(route('admin.roles.index'))
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::whereName('merchandiser')->exists())->toBeTrue();
});

test('the delete permission does not grant the update permission', function () {
    $this->actingAs(userWithPermissions('admin.roles.delete'));

    $role = Role::findOrCreate('merchandiser', 'web');

    $this->put(route('admin.roles.update', $role), ['name' => 'other'])
        ->assertForbidden();
});
