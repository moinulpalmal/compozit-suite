<?php

use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Buyer scoping on the first real buyer-owned model
|--------------------------------------------------------------------------
|
| `tests/Feature/Admin/BuyerScopeTest.php` proves the trait against a throwaway
| model, which keeps the mechanism honest independently of any module. This file
| proves it landed on a real table — `purchase_orders` — with nothing but the one
| `use BuyerScoped;` the contract promised (ARCHITECTURE.md §9.2).
|
| `po_line_items` is the interesting case: it has no `buyer_id` and deliberately
| does **not** use the trait, because it reaches its buyer through its parent.
| That is a stated limit of the mechanism, so it is pinned here rather than left
| for someone to discover and "fix" with a scope that joins.
|
*/

beforeEach(function (): void {
    $this->mine = Buyer::factory()->create(['name' => 'Mine']);
    $this->theirs = Buyer::factory()->create(['name' => 'Theirs']);

    $this->ordersMine = PurchaseOrder::factory()->count(2)->create(['buyer_id' => $this->mine->id]);
    $this->ordersTheirs = PurchaseOrder::factory()->count(3)->create(['buyer_id' => $this->theirs->id]);
});

test('a user sees only the purchase orders of buyers they hold', function () {
    $user = User::factory()->create();
    $user->buyers()->attach($this->mine);

    $this->actingAs($user);

    expect(PurchaseOrder::query()->pluck('buyer_id')->unique()->all())->toBe([$this->mine->id])
        ->and(PurchaseOrder::query()->count())->toBe(2);
});

test('a user with no buyer access sees nothing, which is a legitimate state', function () {
    // A new hire pending assignment. The surfaces render
    // `components/shared/no-buyer-access.tsx` rather than an empty table, so
    // "no access" never reads as "no data".
    $this->actingAs(User::factory()->create());

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('the all-buyer-access flag sees every order without any pivot rows', function () {
    $user = User::factory()->create(['all_buyer_access' => true]);

    $this->actingAs($user);

    // The flag is the grant; materialising a row per buyer would make revocation
    // lossy and needs synchronising every time a buyer is created.
    expect($user->buyers()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(5);
});

test('a super admin sees every order', function () {
    $this->actingAs(superAdmin());

    // `Gate::before` grants abilities and does nothing for row scoping, so
    // `seesAllBuyers()` names the role explicitly — the §9.1 exception. Without
    // it a newly promoted super admin would open an empty application.
    expect(PurchaseOrder::query()->count())->toBe(5);
});

test('imports are scoped the same way as the orders they produced', function () {
    $user = User::factory()->create();
    $user->buyers()->attach($this->mine);

    $this->actingAs($user);

    expect(PoImport::query()->pluck('buyer_id')->unique()->all())->toBe([$this->mine->id]);
});

test('line items carry no buyer of their own and are reached through their order', function () {
    PoLineItem::factory()->count(2)->create(['purchase_order_id' => $this->ordersMine[0]->id]);
    PoLineItem::factory()->count(4)->create(['purchase_order_id' => $this->ordersTheirs[0]->id]);

    $user = User::factory()->create();
    $user->buyers()->attach($this->mine);

    $this->actingAs($user);

    /*
     * `po_line_items` is intentionally unscoped — §9.2 says a model that reaches
     * its buyer through a parent needs its own column rather than a scope that
     * joins, and it has neither. Querying it directly therefore sees everything,
     * which is exactly why nothing should.
     */
    expect(PoLineItem::query()->count())->toBe(6);

    // The supported path is scoped, because the parent is.
    expect(PurchaseOrder::query()->withCount('lineItems')->get()->sum('line_items_count'))->toBe(2);
});

test('the escape hatch is explicit and still works', function () {
    $this->actingAs(User::factory()->create());

    // Reads as the exception it is, which is the whole reason the trait supplies
    // it rather than leaving callers to spell out `withoutGlobalScope`.
    expect(PurchaseOrder::withoutBuyerScope()->count())->toBe(5);
});

test('with no authenticated actor the scope does not filter', function () {
    // Seeders, queue jobs, the scheduler and console commands are system context.
    // Failing closed there would make them silently no-op. Pinned deliberately —
    // do not "fix" it.
    expect(PurchaseOrder::query()->count())->toBe(5);
});
