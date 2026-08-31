<?php

use App\Enums\RecordStatus;
use App\Models\Admin\Designation;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.designations.index'))->assertRedirect(route('login'));
});

test('each action is denied without its own permission', function (string $method, string $route) {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    $designation = Designation::factory()->create();

    $this->{$method}(route($route, $designation), designationPayload())->assertForbidden();
})->with([
    ['post', 'admin.designations.store'],
    ['put', 'admin.designations.update'],
    ['delete', 'admin.designations.destroy'],
]);

test('the list is denied without the view permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.designations.index'))->assertForbidden();
});

test('the list shows every designation with its holder count', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    $held = Designation::factory()->create(['name' => 'Merchandiser']);
    Designation::factory()->inactive()->create(['name' => 'Retired Title']);

    User::factory()->count(2)->create(['designation_id' => $held->id]);

    $this->get(route('admin.designations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/designations/index')
            // Inactive ones are listed here — this screen is where they are
            // reactivated, unlike the user form's picker.
            ->has('designations.data', 2)
            ->where('designations.data.0.name', 'Merchandiser')
            ->where('designations.data.0.users_count', 2)
            ->where('designations.data.0.is_deletable', false)
            ->where('designations.data.1.is_deletable', true));
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a designation is created', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    $this->post(route('admin.designations.store'), designationPayload())
        ->assertSessionHasNoErrors();

    $designation = Designation::query()->where('name', 'Senior Merchandiser')->firstOrFail();

    expect($designation->short_form)->toBe('SMER')
        ->and($designation->status)->toBe(RecordStatus::Active);
});

test('the name is required', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    $this->from(route('admin.designations.index'))
        ->post(route('admin.designations.store'), designationPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

test('the name is unique', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    Designation::factory()->create(['name' => 'Senior Merchandiser']);

    $this->from(route('admin.designations.index'))
        ->post(route('admin.designations.store'), designationPayload())
        ->assertSessionHasErrors('name');
});

test('the short form is optional', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    $this->post(route('admin.designations.store'), designationPayload(['short_form' => null]))
        ->assertSessionHasNoErrors();

    expect(Designation::query()->where('name', 'Senior Merchandiser')->value('short_form'))->toBeNull();
});

test('several designations may have no short form', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    Designation::factory()->withoutShortForm()->create();

    // A unique index allows repeated NULLs on both MySQL and SQLite; this
    // pins that so nobody "fixes" the column to NOT NULL by accident.
    $this->post(route('admin.designations.store'), designationPayload(['short_form' => null]))
        ->assertSessionHasNoErrors();
});

test('the short form is unique when given', function () {
    $this->actingAs(userWithPermissions('admin.designations.create'));

    Designation::factory()->create(['short_form' => 'SMER']);

    $this->from(route('admin.designations.index'))
        ->post(route('admin.designations.store'), designationPayload())
        ->assertSessionHasErrors('short_form');
});

test('a designation keeps its own name and short form when updated', function () {
    $this->actingAs(userWithPermissions('admin.designations.update'));

    $designation = Designation::factory()->create([
        'name' => 'Senior Merchandiser',
        'short_form' => 'SMER',
    ]);

    $this->put(route('admin.designations.update', $designation), designationPayload())
        ->assertSessionHasNoErrors();
});

/*
|--------------------------------------------------------------------------
| Deactivate and delete are two different verbs
|--------------------------------------------------------------------------
|
| `status` is not `deleted_at`. Deactivating retires a title from the user
| form while leaving its holders alone; deleting removes the row and is
| refused while anybody — soft-deleted users included — still holds it.
|
*/

test('a designation nobody holds is deleted', function () {
    $this->actingAs(userWithPermissions('admin.designations.delete'));

    $designation = Designation::factory()->create();

    $response = $this->delete(route('admin.designations.destroy', $designation))
        ->assertSessionHasNoErrors();

    assertToast($response, 'success');

    expect(Designation::query()->whereKey($designation->id)->exists())->toBeFalse();
});

test('a designation a user holds is not deleted', function () {
    $this->actingAs(userWithPermissions('admin.designations.delete'));

    $designation = Designation::factory()->create();
    $holder = User::factory()->create(['designation_id' => $designation->id]);

    $response = $this->from(route('admin.designations.index'))
        ->delete(route('admin.designations.destroy', $designation));

    // A warning, not an error: reassigning the holder clears the refusal.
    assertToast($response, 'warning');

    expect(Designation::query()->whereKey($designation->id)->exists())->toBeTrue()
        ->and($holder->refresh()->designation_id)->toBe($designation->id);
});

test('a designation held only by a soft-deleted user is not deleted', function () {
    $this->actingAs(userWithPermissions('admin.designations.delete'));

    $designation = Designation::factory()->create();
    User::factory()->create(['designation_id' => $designation->id])->delete();

    // The holder is on the Historical tab. Deleting the designation under them
    // would silently blank the field if they were ever restored.
    $this->from(route('admin.designations.index'))
        ->delete(route('admin.designations.destroy', $designation));

    expect(Designation::query()->whereKey($designation->id)->exists())->toBeTrue();
});

test('deactivating keeps the designation and its holders', function () {
    $this->actingAs(userWithPermissions('admin.designations.update'));

    $designation = Designation::factory()->create();
    $holder = User::factory()->create(['designation_id' => $designation->id]);

    $this->put(route('admin.designations.update', $designation), designationPayload([
        'name' => $designation->name,
        'short_form' => $designation->short_form,
        'status' => RecordStatus::Inactive->value,
    ]))->assertSessionHasNoErrors();

    expect($designation->refresh()->status)->toBe(RecordStatus::Inactive)
        ->and($holder->refresh()->designation_id)->toBe($designation->id);
});

test('a deactivated designation is not offered to a new user', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    Designation::factory()->create(['name' => 'Offered']);
    Designation::factory()->inactive()->create(['name' => 'Retired']);

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $offered = collect($page->toArray()['props']['designations'])->pluck('label');

            expect($offered)->toContain('Offered')->not->toContain('Retired');
        });
});

test('the users filter lists deactivated designations too', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    Designation::factory()->inactive()->create(['name' => 'Retired']);

    // A retired title still has holders, so an admin has to be able to find
    // them — the filter and the picker deliberately differ.
    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $filters = collect($page->toArray()['props']['designationFilters'])->pluck('label');

            expect($filters)->toContain('Retired');
        });
});

test('paginating the designation list does not paginate the user form picker', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    // Comfortably more than the list's 25-row page.
    Designation::factory()->count(40)->create();

    /*
     * A list and its picker are different queries against the same table. The
     * list is paginated; `assignableOptions()` must still offer every
     * assignable title, or a user on page 2 of designations could not be given
     * one of the first 25.
     */
    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('designations', 40)
            ->has('designationFilters', 40));
});

/*
|--------------------------------------------------------------------------
| The options endpoint — the combobox's async source
|--------------------------------------------------------------------------
|
| `<Combobox searchUrl>` reads this. The shape is a convention every later
| options endpoint follows, so it is pinned here rather than left implicit.
| See ARCHITECTURE.md §8.5.
|
*/

test('the options endpoint is denied without the view permission', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.designations.options'))->assertForbidden();
});

test('the options endpoint returns the agreed shape', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => 'Merchandiser', 'short_form' => 'MER']);

    $this->getJson(route('admin.designations.options'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['value', 'label', 'hint']]])
        ->assertJsonPath('data.0.label', 'Merchandiser')
        ->assertJsonPath('data.0.hint', 'MER');
});

test('the options endpoint offers active designations only', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => 'Offered']);
    Designation::factory()->inactive()->create(['name' => 'Retired']);

    $response = $this->getJson(route('admin.designations.options'))->assertOk();

    expect(collect($response->json('data'))->pluck('label'))
        ->toContain('Offered')
        ->not->toContain('Retired');
});

test('the options endpoint matches the term by prefix', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => 'Merchandiser', 'short_form' => 'MER']);
    Designation::factory()->create(['name' => 'Cutter', 'short_form' => 'CUT']);

    // Prefix, not contains — the same indexable contract as the user search.
    $matched = $this->getJson(route('admin.designations.options', ['q' => 'Merc']))->json('data');
    $unmatched = $this->getJson(route('admin.designations.options', ['q' => 'erch']))->json('data');

    expect($matched)->toHaveCount(1)
        ->and($matched[0]['label'])->toBe('Merchandiser')
        ->and($unmatched)->toHaveCount(0);
});

test('the options endpoint matches the short form too', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => 'Quality Inspector', 'short_form' => 'QI']);

    expect($this->getJson(route('admin.designations.options', ['q' => 'QI']))->json('data'))
        ->toHaveCount(1);
});

test('a wildcard in the options term is escaped, not honoured', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => 'Merchandiser']);

    expect($this->getJson(route('admin.designations.options', ['q' => '%']))->json('data'))
        ->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Actor stamping
|--------------------------------------------------------------------------
*/

test('the shared observer stamps who created and who last changed a designation', function () {
    $actor = userWithPermissions('admin.designations.create', 'admin.designations.update');

    $this->actingAs($actor)->post(route('admin.designations.store'), designationPayload());

    $designation = Designation::query()->where('name', 'Senior Merchandiser')->firstOrFail();

    expect($designation->inserted_by)->toBe($actor->id)
        ->and($designation->last_updated_by)->toBeNull();

    $this->put(route('admin.designations.update', $designation), designationPayload([
        'name' => 'Lead Merchandiser',
    ]));

    expect($designation->refresh()->last_updated_by)->toBe($actor->id);
});

test('a designation created with no authenticated actor is stamped with nobody', function () {
    expect(Designation::factory()->create()->inserted_by)->toBeNull();
});

/**
 * A valid designation payload, with overrides applied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function designationPayload(array $overrides = []): array
{
    return [
        'name' => 'Senior Merchandiser',
        'short_form' => 'SMER',
        'status' => RecordStatus::Active->value,
        ...$overrides,
    ];
}
