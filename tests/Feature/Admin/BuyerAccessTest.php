<?php

use App\Models\Admin\Buyer;
use App\Models\User;

/**
 * An administrator who may assign access and can see every buyer themselves.
 *
 * The second half matters: nobody grants access they do not hold, so an actor
 * with the permission but no access of their own can grant nothing.
 */
function accessAdmin(): User
{
    $admin = userWithPermissions('admin.buyer-access.update', 'admin.buyer-access.view', 'admin.users.view');

    $admin->forceFill(['all_buyer_access' => true])->save();

    return $admin;
}

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('assigning buyer access is denied without the permission', function () {
    $this->actingAs(userWithPermissions('admin.users.update'));

    $user = User::factory()->create();

    $this->put(route('admin.users.buyer-access', $user), ['buyers' => []])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Assignment
|--------------------------------------------------------------------------
*/

test('buyers are granted to a user', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $buyers = Buyer::factory()->count(2)->create();

    assertToast(
        $this->put(route('admin.users.buyer-access', $user), [
            'buyers' => $buyers->pluck('id')->all(),
        ]),
        'success',
    );

    expect($user->refresh()->buyers()->pluck('buyers.id')->all())
        ->toEqualCanonicalizing($buyers->pluck('id')->all())
        ->and($user->all_buyer_access)->toBeFalse();
});

test('a submitted set replaces the previous one', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $old = Buyer::factory()->create();
    $new = Buyer::factory()->create();

    $user->buyers()->attach($old);

    $this->put(route('admin.users.buyer-access', $user), ['buyers' => [$new->id]])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->buyers()->pluck('buyers.id')->all())->toBe([$new->id]);
});

test('granting all buyers clears the individual grants', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $user->buyers()->attach(Buyer::factory()->count(2)->create());

    $this->put(route('admin.users.buyer-access', $user), [
        'all_buyer_access' => '1',
        'buyers' => [],
    ])->assertSessionHasNoErrors();

    // The flag *is* the grant. Rows kept underneath it would recreate exactly
    // the ambiguity this design removed — see ARCHITECTURE.md §9.2.
    expect($user->refresh()->all_buyer_access)->toBeTrue()
        ->and($user->buyers()->count())->toBe(0)
        ->and($user->seesAllBuyers())->toBeTrue();
});

test('the buyers a request submits are ignored when it also grants all buyers', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $buyer = Buyer::factory()->create();

    $this->put(route('admin.users.buyer-access', $user), [
        'all_buyer_access' => '1',
        'buyers' => [$buyer->id],
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->buyers()->count())->toBe(0);
});

test('a user may be left with no buyers at all', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $user->buyers()->attach(Buyer::factory()->create());

    $this->put(route('admin.users.buyer-access', $user), ['buyers' => []])
        ->assertSessionHasNoErrors();

    // A legitimate state — a new hire pending assignment. The surfaces say so
    // rather than showing an empty table.
    expect($user->refresh()->buyers()->count())->toBe(0)
        ->and($user->seesAllBuyers())->toBeFalse();
});

test('a malformed nested buyers payload is refused rather than silently ignored', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $buyer = Buyer::factory()->create();

    /*
     * `[[id]]` is what `buyers[][]` decodes to — the shape the access dialog
     * actually submitted until `Combobox` stopped double-bracketing the name it
     * was given. The server-side tests here post arrays directly, so they were
     * green against markup no browser could have used; this pins the server half
     * so the failure is at least loud if it ever recurs.
     */
    $this->from(route('admin.users.index'))
        ->put(route('admin.users.buyer-access', $user), ['buyers' => [[$buyer->id]]])
        ->assertSessionHasErrors('buyers.0');

    expect($user->refresh()->buyers()->count())->toBe(0);
});

test('an unknown buyer is a validation error', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();

    $this->from(route('admin.users.index'))
        ->put(route('admin.users.buyer-access', $user), ['buyers' => [9999]])
        ->assertSessionHasErrors('buyers.0');
});

/*
|--------------------------------------------------------------------------
| Escalation guards
|--------------------------------------------------------------------------
|
| These live in `BuyerAccessService`, not a policy: `Gate::before` grants a super
| admin every ability, so a policy denial would be bypassed for exactly the
| account a privilege guard exists to bind. See ARCHITECTURE.md §9.2.
|
*/

test('nobody changes their own buyer access', function () {
    $admin = accessAdmin();
    $this->actingAs($admin);

    $buyer = Buyer::factory()->create();

    assertToast(
        $this->put(route('admin.users.buyer-access', $admin), ['buyers' => [$buyer->id]]),
        'error',
    );

    expect($admin->refresh()->buyers()->count())->toBe(0);
});

test('a super admin cannot change their own buyer access either', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    $buyer = Buyer::factory()->create();

    // The point of the guard living in the service: `Gate::before` would have
    // waved this straight through a policy.
    assertToast(
        $this->put(route('admin.users.buyer-access', $admin), ['buyers' => [$buyer->id]]),
        'error',
    );
});

test('you cannot grant all-buyer access unless you hold it', function () {
    $admin = userWithPermissions('admin.buyer-access.update');
    $admin->buyers()->attach(Buyer::factory()->create());

    $this->actingAs($admin);

    $user = User::factory()->create();

    assertToast(
        $this->put(route('admin.users.buyer-access', $user), ['all_buyer_access' => '1']),
        'error',
    );

    expect($user->refresh()->all_buyer_access)->toBeFalse();
});

test('you cannot grant a buyer you cannot see yourself', function () {
    $mine = Buyer::factory()->create();
    $theirs = Buyer::factory()->create();

    $admin = userWithPermissions('admin.buyer-access.update');
    $admin->buyers()->attach($mine);

    $this->actingAs($admin);

    $user = User::factory()->create();

    assertToast(
        $this->put(route('admin.users.buyer-access', $user), [
            'buyers' => [$mine->id, $theirs->id],
        ]),
        'error',
    );

    expect($user->refresh()->buyers()->count())->toBe(0);
});

test('a limited administrator may grant the buyers they do hold', function () {
    $mine = Buyer::factory()->create();

    $admin = userWithPermissions('admin.buyer-access.update');
    $admin->buyers()->attach($mine);

    $this->actingAs($admin);

    $user = User::factory()->create();

    assertToast(
        $this->put(route('admin.users.buyer-access', $user), ['buyers' => [$mine->id]]),
        'success',
    );

    expect($user->refresh()->buyers()->pluck('buyers.id')->all())->toBe([$mine->id]);
});

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

test('a soft-deleted user keeps their access and has it back on restore', function () {
    $this->actingAs(accessAdmin());

    $user = User::factory()->create();
    $buyer = Buyer::factory()->create();
    $user->buyers()->attach($buyer);

    $user->delete();

    // No cascade fires on a soft delete, so the grants survive — which is what
    // makes a restored account usable again rather than silently blind.
    expect($user->buyers()->count())->toBe(1);

    $user->restore();

    expect($user->refresh()->buyers()->pluck('buyers.id')->all())->toBe([$buyer->id]);
});

/*
|--------------------------------------------------------------------------
| The users list
|--------------------------------------------------------------------------
*/

test('the users list carries buyer access for someone allowed to see it', function () {
    $admin = accessAdmin();
    $this->actingAs($admin);

    $user = User::factory()->create();
    $user->buyers()->attach(Buyer::factory()->count(2)->create());

    $this->get(route('admin.users.index', ['filter' => ['employee_id' => $user->employee_id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.buyers_count', 2)
            ->where('users.data.0.all_buyer_access', false)
            ->has('users.data.0.buyers', 2));
});

test('the users list omits buyer access without the view permission', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create()->buyers()->attach(Buyer::factory()->create());

    // Absent rather than zero: a `0` from a relation that was never loaded is a
    // lie, and the column hides itself on the same permission.
    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('users.data.0.buyers_count'));
});
