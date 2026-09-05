<?php

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('users without the view permission are denied', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.users.index'))->assertForbidden();
});

test('users with the view permission see the active list', function () {
    $actor = userWithPermissions('admin.users.view');

    User::factory()->create(['name' => 'Active Person']);
    User::factory()->create(['name' => 'Deleted Person'])->delete();

    $this->actingAs($actor)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->where('filters.view', 'active')
            ->where('counts.trashed', 1));
});

test('the historical tab lists only soft-deleted users', function () {
    $actor = userWithPermissions('admin.users.view');

    User::factory()->create(['name' => 'Active Person']);
    User::factory()->create(['name' => 'Deleted Person'])->delete();

    $this->actingAs($actor)
        ->get(route('admin.users.index', ['view' => 'trashed']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Deleted Person'));
});

/*
|--------------------------------------------------------------------------
| Filtering, sorting, searching, paging
|--------------------------------------------------------------------------
|
| Search is a **prefix** match on one named field. Matching from anywhere in
| the string cannot use an index; matching from the start can. The mid-string
| test below pins that contract deliberately.
|
*/

test('search matches an employee id by prefix', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['employee_id' => '90210']);
    User::factory()->create(['employee_id' => '77777']);

    $this->get(route('admin.users.index', ['filter' => ['employee_id' => '902']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.employee_id', '90210'));
});

test('search does not match mid-string', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['employee_id' => '90210']);

    $this->get(route('admin.users.index', ['filter' => ['employee_id' => '021']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 0));
});

test('search is scoped to the chosen field', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['name' => 'Zubair', 'employee_id' => '55501']);

    // The term matches the employee ID, but it was typed into the name cell —
    // each cell filters its own column and nothing else.
    $this->get(route('admin.users.index', ['filter' => ['name' => '555']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 0));
});

test('a wildcard in the search term is escaped, not honoured', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['name' => 'Rashida']);

    $this->get(route('admin.users.index', ['filter' => ['name' => '%']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 0));
});

test('users can be searched by mobile number prefix', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['personal_mobile_no' => '01712345678']);
    User::factory()->create(['personal_mobile_no' => '01911112222']);

    $this->get(route('admin.users.index', ['filter' => ['personal_mobile_no' => '01712']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.personal_mobile_no', '01712345678'));
});

test('the list sorts by an allow-listed column in both directions', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['employee_id' => '11111']);
    User::factory()->create(['employee_id' => '99999']);

    $this->get(route('admin.users.index', ['sort' => 'employee_id', 'direction' => 'asc']))
        ->assertInertia(fn ($page) => $page->where('users.data.0.employee_id', '11111'));

    $this->get(route('admin.users.index', ['sort' => 'employee_id', 'direction' => 'desc']))
        ->assertInertia(fn ($page) => $page->where('users.data.0.employee_id', '99999'));
});

test('an unknown sort column is rejected rather than reaching the database', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create();

    $this->get(route('admin.users.index', ['sort' => 'name; DROP TABLE users']))
        ->assertSessionHasErrors('sort');

    // The point of the guard: the table is still there.
    expect(User::query()->count())->toBeGreaterThan(0);
});

test('an unknown filter column is rejected', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $this->get(route('admin.users.index', ['filter' => ['password' => 'x']]))
        ->assertSessionHasErrors('filter');
});

test('the list filters by gender and by status', function () {
    // The factory assigns a random gender, so the actor is pinned to a value
    // neither assertion filters on — otherwise this test flakes.
    $actor = userWithPermissions('admin.users.view');
    $actor->update(['gender' => 'O']);

    $this->actingAs($actor);

    User::factory()->create(['name' => 'Female Person', 'gender' => 'F']);
    User::factory()->create(['name' => 'Male Person', 'gender' => 'M']);
    User::factory()->inactive()->create(['name' => 'Disabled Person', 'gender' => 'M']);

    $this->get(route('admin.users.index', ['filter' => ['gender' => 'F']]))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Female Person'));

    $this->get(route('admin.users.index', ['filter' => ['status' => RecordStatus::Inactive->value]]))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Disabled Person'));
});

test('the list is paginated and keeps its filters across pages', function () {
    $actor = userWithPermissions('admin.users.view');
    $actor->update(['gender' => 'O']);

    $this->actingAs($actor);

    User::factory()->count(30)->create(['gender' => 'F']);

    $this->get(route('admin.users.index', ['filter' => ['gender' => 'F']]))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', ListRequest::DEFAULT_PER_PAGE)
            ->where('users.total', 30)
            ->where('users.current_page', 1));

    $this->get(route('admin.users.index', ['filter' => ['gender' => 'F'], 'page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('users.current_page', 2)
            ->where('filters.filter.gender', 'F'));
});

test('a user is created with roles and a verified email', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    Role::findOrCreate('merchandiser', 'web');

    $this->post(route('admin.users.store'), userPayload(['roles' => ['merchandiser']]))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('employee_id', '44821')->firstOrFail();

    expect($user->name)->toBe('New Person')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->status)->toBe(RecordStatus::Active)
        ->and($user->hasRole('merchandiser'))->toBeTrue()
        ->and(Hash::check(compliantPassword(), $user->password))->toBeTrue();
});

test('creating a user requires the create permission', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $this->post(route('admin.users.store'), userPayload())->assertForbidden();
});

test('the employee id format is enforced', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    $this->from(route('admin.users.index'))
        ->post(route('admin.users.store'), userPayload(['employee_id' => 'no spaces!']))
        ->assertSessionHasErrors('employee_id');
});

test('mobile numbers must be bangladeshi', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    $this->from(route('admin.users.index'))
        ->post(route('admin.users.store'), userPayload(['personal_mobile_no' => '01212345678']))
        ->assertSessionHasErrors('personal_mobile_no');
});

test('an employee id belonging to a soft-deleted user is refused', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    User::factory()->create(['employee_id' => '44821'])->delete();

    $this->from(route('admin.users.index'))
        ->post(route('admin.users.store'), userPayload())
        ->assertSessionHasErrors('employee_id');
});

test('the availability endpoint counts soft-deleted users as taken', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    User::factory()->create(['employee_id' => '44821'])->delete();

    $this->getJson(route('admin.users.availability', ['field' => 'employee_id', 'value' => '44821']))
        ->assertOk()
        ->assertJson(['available' => false]);

    $this->getJson(route('admin.users.availability', ['field' => 'employee_id', 'value' => '55555']))
        ->assertOk()
        ->assertJson(['available' => true]);
});

test('a user is updated', function () {
    $this->actingAs(userWithPermissions('admin.users.update'));

    $user = User::factory()->create();

    $this->put(route('admin.users.update', $user), userPayload(except: ['password', 'password_confirmation']))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('New Person')
        ->and($user->employee_id)->toBe('44821');
});

test('deleting a user soft-deletes them', function () {
    $this->actingAs(userWithPermissions('admin.users.delete'));

    $user = User::factory()->create();

    $this->delete(route('admin.users.destroy', $user));

    $this->assertSoftDeleted($user);
});

test('a soft-deleted user cannot authenticate', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->post(route('login.store'), [
        'employee_id' => $user->employee_id,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('a soft-deleted user is restored', function () {
    $this->actingAs(userWithPermissions('admin.users.restore'));

    $user = User::factory()->create();
    $user->delete();

    $this->put(route('admin.users.restore', $user));

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

test('a user is permanently deleted', function () {
    $this->actingAs(userWithPermissions('admin.users.force-delete'));

    $user = User::factory()->create();
    $user->delete();

    $this->delete(route('admin.users.force-delete', $user));

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
});

test('an administrator sets another password without knowing the old one', function () {
    $this->actingAs(userWithPermissions('admin.users.reset-password'));

    $user = User::factory()->create();

    $this->put(route('admin.users.password', $user), [
        'password' => compliantPassword(),
        'password_confirmation' => compliantPassword(),
    ])->assertSessionHasNoErrors();

    expect(Hash::check(compliantPassword(), $user->refresh()->password))->toBeTrue();
});

test('roles are replaced wholesale', function () {
    $this->actingAs(userWithPermissions('admin.users.assign-roles'));

    Role::findOrCreate('merchandiser', 'web');
    Role::findOrCreate('viewer', 'web');

    $user = User::factory()->create();
    $user->assignRole('viewer');

    $this->put(route('admin.users.roles', $user), ['roles' => ['merchandiser']])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->hasRole('merchandiser'))->toBeTrue()
        ->and($user->hasRole('viewer'))->toBeFalse();
});

test('updating a profile does not imply the power to assign roles', function () {
    $this->actingAs(userWithPermissions('admin.users.update'));

    $user = User::factory()->create();

    $this->put(route('admin.users.roles', $user), ['roles' => []])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Escalation guards
|--------------------------------------------------------------------------
|
| These live in `UserService` and `RoleAssignmentRules`, not in `UserPolicy`:
| `Gate::before` grants a super admin every ability, so a policy denial would
| be bypassed for the very account the guard protects against.
|
*/

test('a non super admin cannot grant the super-admin role', function () {
    $this->actingAs(userWithPermissions('admin.users.assign-roles'));

    Role::findOrCreate(Role::SUPER_ADMIN, 'web');

    $user = User::factory()->create();

    $this->from(route('admin.users.index'))
        ->put(route('admin.users.roles', $user), ['roles' => [Role::SUPER_ADMIN]])
        ->assertSessionHasErrors('roles.0');

    expect($user->refresh()->hasRole(Role::SUPER_ADMIN))->toBeFalse();
});

test('a non super admin cannot revoke the super-admin role', function () {
    $this->actingAs(userWithPermissions('admin.users.assign-roles'));

    $target = superAdmin();
    superAdmin();

    $this->from(route('admin.users.index'))
        ->put(route('admin.users.roles', $target), ['roles' => []]);

    expect($target->refresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

test('nobody changes their own roles', function () {
    $actor = userWithPermissions('admin.users.assign-roles');
    Role::findOrCreate('merchandiser', 'web');

    $this->actingAs($actor)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.roles', $actor), ['roles' => ['merchandiser']]);

    expect($actor->refresh()->hasRole('merchandiser'))->toBeFalse();
});

test('nobody deletes their own account from the admin screen', function () {
    $actor = userWithPermissions('admin.users.delete');

    $this->actingAs($actor)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $actor));

    $this->assertNotSoftDeleted($actor);
});

test('the last super admin cannot be deleted', function () {
    $actor = userWithPermissions('admin.users.delete');
    $target = superAdmin();

    $response = $this->actingAs($actor)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $target));

    // An error, not a warning: unlike a designation with holders, there is no
    // action the actor can take that would make this delete succeed.
    assertToast($response, 'error');

    $this->assertNotSoftDeleted($target);
});

test('a super admin can be deleted once another one exists', function () {
    $actor = userWithPermissions('admin.users.delete');
    $target = superAdmin();
    superAdmin();

    $this->actingAs($actor)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $target));

    $this->assertSoftDeleted($target);
});

test('the last super admin cannot be deactivated', function () {
    $actor = userWithPermissions('admin.users.update');
    $target = superAdmin();

    $this->actingAs($actor)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $target), userPayload(
            ['employee_id' => $target->employee_id, 'email' => $target->email, 'status' => RecordStatus::Inactive->value],
            except: ['password', 'password_confirmation'],
        ));

    expect($target->refresh()->status)->toBe(RecordStatus::Active);
});

/*
|--------------------------------------------------------------------------
| Actor stamping
|--------------------------------------------------------------------------
*/

test('the observer stamps who created and who last changed a user', function () {
    $actor = userWithPermissions('admin.users.create', 'admin.users.update');

    $this->actingAs($actor)->post(route('admin.users.store'), userPayload());

    $user = User::query()->where('employee_id', '44821')->firstOrFail();

    expect($user->inserted_by)->toBe($actor->id)
        ->and($user->last_updated_by)->toBeNull();

    $this->put(route('admin.users.update', $user), userPayload(
        ['name' => 'Renamed Person'],
        except: ['password', 'password_confirmation'],
    ));

    expect($user->refresh()->last_updated_by)->toBe($actor->id);
});

test('seeded users are stamped with nobody', function () {
    expect(User::factory()->create()->inserted_by)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Designations on the user surface
|--------------------------------------------------------------------------
|
| `designation_id` is nullable in the database and required in the form
| requests. Both halves of that split are pinned below: an old row with no
| designation must still load, and a new one must not be creatable without.
|
*/

test('a user cannot be created without a designation', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    $this->from(route('admin.users.index'))
        ->post(route('admin.users.store'), userPayload(except: ['designation_id']))
        ->assertSessionHasErrors('designation_id');
});

test('a deactivated designation cannot be assigned to a new user', function () {
    $this->actingAs(userWithPermissions('admin.users.create'));

    $retired = Designation::factory()->inactive()->create();

    $this->from(route('admin.users.index'))
        ->post(route('admin.users.store'), userPayload(['designation_id' => $retired->id]))
        ->assertSessionHasErrors('designation_id');
});

test('a user keeps a designation that was deactivated after they were given it', function () {
    $this->actingAs(userWithPermissions('admin.users.update'));

    $retired = Designation::factory()->inactive()->create();
    $user = User::factory()->create(['designation_id' => $retired->id]);

    // Editing anything else must not be blocked by a value the admin never
    // touched — the rule grants the user's own current designation.
    $this->put(route('admin.users.update', $user), userPayload(
        [
            'name' => 'Renamed Person',
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'designation_id' => $retired->id,
        ],
        except: ['password', 'password_confirmation'],
    ))->assertSessionHasNoErrors();

    expect($user->refresh()->designation_id)->toBe($retired->id);
});

test('a user with no designation still loads on the list', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['name' => 'Legacy Person', 'designation_id' => null]);

    $this->get(route('admin.users.index', ['filter' => ['name' => 'Legacy']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.designation', null));
});

test('the list filters by designation', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $merchandiser = Designation::factory()->create(['name' => 'Merchandiser']);
    $cutter = Designation::factory()->create(['name' => 'Cutter']);

    User::factory()->create(['name' => 'Ayesha', 'designation_id' => $merchandiser->id]);
    User::factory()->create(['name' => 'Bilal', 'designation_id' => $cutter->id]);

    $this->get(route('admin.users.index', ['filter' => ['designation_id' => $merchandiser->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Ayesha')
            ->where('users.data.0.designation', 'Merchandiser')
            ->where('filters.filter.designation_id', (string) $merchandiser->id));
});

test('an unknown designation filter is rejected', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $this->from(route('admin.users.index'))
        ->get(route('admin.users.index', ['filter' => ['designation_id' => 999999]]))
        ->assertSessionHasErrors('filter.designation_id');
});

test('the edit picker offers a deactivated designation this page still holds', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    $retired = Designation::factory()->inactive()->create(['name' => 'Retired Title']);
    $unused = Designation::factory()->inactive()->create(['name' => 'Never Used']);
    Designation::factory()->create(['name' => 'Current Title']);

    User::factory()->create(['designation_id' => $retired->id]);

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $offered = collect($page->toArray()['props']['designations'])->pluck('label');

            // The retired title a row holds is offered so saving that row does
            // not blank it; the retired title nobody holds is not.
            expect($offered)->toContain('Current Title')
                ->toContain('Retired Title')
                ->not->toContain('Never Used');
        });
});

/**
 * A valid user payload, with overrides applied and keys removed.
 *
 * @param  array<string, mixed>  $overrides
 * @param  list<string>  $except
 * @return array<string, mixed>
 */
function userPayload(array $overrides = [], array $except = []): array
{
    $attributes = [
        'name' => 'New Person',
        'employee_id' => '44821',
        'email' => 'new.person@example.com',
        'personal_mobile_no' => '01712345678',
        'official_mobile_no' => null,
        'official_extension_no' => '204',
        'gender' => 'F',
        // Required by the form requests even though the column is nullable —
        // reuse the first active designation so repeated calls in one test do
        // not litter the table.
        'designation_id' => (Designation::query()->active()->first()
            ?? Designation::factory()->create())->id,
        'status' => RecordStatus::Active->value,
        'approval_authority' => 0,
        'password' => compliantPassword(),
        'password_confirmation' => compliantPassword(),
        ...$overrides,
    ];

    return array_diff_key($attributes, array_flip($except));
}
