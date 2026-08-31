<?php

use App\Concerns\BuyerScoped;
use App\Models\Admin\Buyer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Buyer scope
|--------------------------------------------------------------------------
|
| The row-level half of ARCHITECTURE.md §9.2. No buyer-owned table exists yet —
| purchase orders, tech packs and bookings are all still to be built — so the
| scope is proven against the throwaway model below. The first real buyer-owned
| model adds `use BuyerScoped;` and inherits exactly this behaviour.
|
| Every case here is a decision worth pinning, the fail-open one most of all:
| "no authenticated actor means no filtering" is deliberate, and looks like a
| bug to anyone who meets it without this test.
|
*/

class BuyerOwnedThing extends Model
{
    use BuyerScoped;

    protected $table = 'buyer_owned_things';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('buyer_owned_things', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
    });

    $this->buyers = Buyer::factory()->count(3)->create();

    $this->buyers->each(fn (Buyer $buyer) => BuyerOwnedThing::create(['buyer_id' => $buyer->id]));
});

test('with no authenticated actor the scope does not filter', function () {
    // System context: seeders, queue jobs, the scheduler, console commands.
    // Failing closed here would make them silently no-op.
    expect(BuyerOwnedThing::query()->count())->toBe(3);
});

test('a user sees only the rows of the buyers they hold', function () {
    $user = User::factory()->create();
    $user->buyers()->attach($this->buyers->take(2));

    $this->actingAs($user);

    expect(BuyerOwnedThing::query()->pluck('buyer_id')->all())
        ->toEqualCanonicalizing($this->buyers->take(2)->pluck('id')->all());
});

test('a user with no buyers sees nothing', function () {
    $this->actingAs(User::factory()->create());

    // A legitimate state — a new hire pending assignment — which is why the
    // surfaces render `no-buyer-access.tsx` instead of an empty table.
    expect(BuyerOwnedThing::query()->count())->toBe(0);
});

test('the all-buyer-access flag sees everything, including buyers added later', function () {
    $user = User::factory()->create();
    $user->forceFill(['all_buyer_access' => true])->save();

    $this->actingAs($user);

    $late = Buyer::factory()->create();
    BuyerOwnedThing::create(['buyer_id' => $late->id]);

    // The whole point of the flag over materialised rows: nothing had to happen
    // when that buyer was created.
    expect(BuyerOwnedThing::query()->count())->toBe(4);
});

test('a super admin sees everything without holding the flag', function () {
    $admin = superAdmin();

    $this->actingAs($admin);

    // `Gate::before` grants abilities and does nothing for row scoping, so
    // without the super-admin arm of `seesAllBuyers()` this would be 0. Read
    // back from the database: the factory never sets the column, so the value
    // under test is the migration's default rather than an in-memory one.
    expect($admin->fresh()->all_buyer_access)->toBeFalse()
        ->and(BuyerOwnedThing::query()->count())->toBe(3);
});

test('withoutBuyerScope escapes the filter deliberately', function () {
    $this->actingAs(User::factory()->create());

    expect(BuyerOwnedThing::query()->count())->toBe(0)
        ->and(BuyerOwnedThing::withoutBuyerScope()->count())->toBe(3);
});

test('the scope applies to relationship and aggregate queries too', function () {
    $user = User::factory()->create();
    $user->buyers()->attach($this->buyers->first());

    $this->actingAs($user);

    // A global scope is not something a caller can forget, which is the reason
    // it was chosen over an explicit `->visibleTo()` per service.
    expect(BuyerOwnedThing::query()->exists())->toBeTrue()
        ->and(BuyerOwnedThing::query()->max('buyer_id'))->toBe($this->buyers->first()->id);
});

test('the accessible buyer ids are read once per instance', function () {
    $user = User::factory()->create();
    $user->buyers()->attach($this->buyers->first());

    $this->actingAs($user);

    DB::enableQueryLog();

    BuyerOwnedThing::query()->count();
    BuyerOwnedThing::query()->count();
    BuyerOwnedThing::query()->count();

    // Three scoped queries, and `buyer_user` is read once. A global scope runs
    // on every query, so an unmemoised lookup would be a round trip each time.
    $pivotReads = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'buyer_user'))
        ->count();

    expect($pivotReads)->toBe(1);
});
