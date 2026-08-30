<?php

use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use App\Models\User;

/**
 * A valid buyer payload, overridable per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function buyerPayload(array $overrides = []): array
{
    return [
        'name' => 'Zara',
        'code' => 'ZARA',
        'status' => RecordStatus::Active->value,
        ...$overrides,
    ];
}

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('guests are redirected to the login page', function () {
    $this->get(route('admin.buyers.index'))->assertRedirect(route('login'));
});

test('each action is denied without its own permission', function (string $method, string $route) {
    $this->actingAs(userWithPermissions('admin.buyers.view'));

    $buyer = Buyer::factory()->create();

    $this->{$method}(route($route, $buyer), buyerPayload())->assertForbidden();
})->with([
    ['post', 'admin.buyers.store'],
    ['put', 'admin.buyers.update'],
    ['delete', 'admin.buyers.destroy'],
]);

test('the list is denied without the view permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.buyers.index'))->assertForbidden();
});

test('the list shows every buyer with the count of explicit grants', function () {
    $this->actingAs(userWithPermissions('admin.buyers.view'));

    $granted = Buyer::factory()->create(['name' => 'Aaa Buyer', 'code' => 'AAA']);
    Buyer::factory()->inactive()->create(['name' => 'Zzz Retired', 'code' => 'ZZZ']);

    User::factory()->count(2)->create()->each(fn (User $user) => $user->buyers()->attach($granted));

    // An all-access user holds no row, so they must not be counted.
    User::factory()->create()->forceFill(['all_buyer_access' => true])->save();

    $this->get(route('admin.buyers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/buyers/index')
            // Inactive buyers are listed here — this screen is where they are
            // reactivated, unlike the access picker.
            ->has('buyers.data', 2)
            ->where('buyers.data.0.name', 'Aaa Buyer')
            ->where('buyers.data.0.users_count', 2)
            ->where('buyers.data.1.users_count', 0));
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a buyer is created', function () {
    $this->actingAs(userWithPermissions('admin.buyers.create'));

    $this->post(route('admin.buyers.store'), buyerPayload())
        ->assertSessionHasNoErrors();

    $buyer = Buyer::query()->where('name', 'Zara')->firstOrFail();

    expect($buyer->code)->toBe('ZARA')
        ->and($buyer->status)->toBe(RecordStatus::Active);
});

test('the name is required and unique', function (array $payload, string $field) {
    $this->actingAs(userWithPermissions('admin.buyers.create'));

    Buyer::factory()->create(['name' => 'Zara', 'code' => 'ZARA']);

    $this->from(route('admin.buyers.index'))
        ->post(route('admin.buyers.store'), buyerPayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'blank name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Zara', 'code' => 'OTHER'], 'name'],
    'duplicate code' => [['name' => 'Other', 'code' => 'ZARA'], 'code'],
]);

test('the code is optional, and two buyers may both omit it', function () {
    $this->actingAs(userWithPermissions('admin.buyers.create'));

    // Repeated NULLs are legal in a unique index on both MySQL and SQLite —
    // which is what lets rows arrive from the old system without a code.
    $this->post(route('admin.buyers.store'), buyerPayload(['name' => 'First', 'code' => null]))
        ->assertSessionHasNoErrors();

    $this->post(route('admin.buyers.store'), buyerPayload(['name' => 'Second', 'code' => null]))
        ->assertSessionHasNoErrors();

    expect(Buyer::query()->whereNull('code')->count())->toBe(2);
});

test('a buyer keeps its own code when updated', function () {
    $this->actingAs(userWithPermissions('admin.buyers.update'));

    $buyer = Buyer::factory()->create(['name' => 'Zara', 'code' => 'ZARA']);

    $this->put(route('admin.buyers.update', $buyer), buyerPayload(['name' => 'Zara Home']))
        ->assertSessionHasNoErrors();

    expect($buyer->refresh()->name)->toBe('Zara Home');
});

/*
|--------------------------------------------------------------------------
| Deletion
|--------------------------------------------------------------------------
*/

test('deleting a buyer withdraws the access rows that pointed at it', function () {
    $this->actingAs(userWithPermissions('admin.buyers.delete'));

    $buyer = Buyer::factory()->create();
    $user = User::factory()->create();
    $user->buyers()->attach($buyer);

    // Access is a derived permission, not history: it cascades rather than
    // blocking the delete. Facts about a buyer are the opposite case and will
    // populate `BuyerService::deletionBlocker()` as those tables land.
    assertToast($this->delete(route('admin.buyers.destroy', $buyer)), 'success');

    expect(Buyer::query()->whereKey($buyer->id)->exists())->toBeFalse()
        ->and($user->buyers()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Options endpoint
|--------------------------------------------------------------------------
*/

test('the options endpoint matches a prefix, not mid-string', function () {
    $this->actingAs(userWithPermissions('admin.buyers.view'));

    Buyer::factory()->create(['name' => 'Primark', 'code' => 'PRMK']);

    // Prefix keeps the unique indexes seekable — ARCHITECTURE.md §6.3.
    $this->getJson(route('admin.buyers.options', ['q' => 'Prim']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hint', 'PRMK');

    $this->getJson(route('admin.buyers.options', ['q' => 'mark']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the options endpoint offers only active buyers, uncapped by the list page size', function () {
    $this->actingAs(userWithPermissions('admin.buyers.view'));

    Buyer::factory()->count(30)->create();
    Buyer::factory()->inactive()->create();

    // A list and its picker are different queries: paginating the list must
    // never paginate the picker (ARCHITECTURE.md §8.6).
    $this->getJson(route('admin.buyers.options'))
        ->assertOk()
        ->assertJsonCount(30, 'data');
});

test('the options endpoint answers to someone who may only assign access', function () {
    $this->actingAs(userWithPermissions('admin.buyer-access.update'));

    Buyer::factory()->create();

    // The access dialog needs the picker without needing to administer buyers.
    $this->getJson(route('admin.buyers.options'))->assertOk();
});

test('the options endpoint is denied to someone with neither permission', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.buyers.options'))->assertForbidden();
});
