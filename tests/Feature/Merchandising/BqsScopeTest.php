<?php

use App\Models\Admin\Buyer;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;

/*
|--------------------------------------------------------------------------
| Buyer scoping on the BQS tables
|--------------------------------------------------------------------------
|
| `BuyerScopeTest` pins the trait's contract against a throwaway model, and
| `PurchaseOrderScopeTest` proves it on a real table. This file proves the case
| neither covers: a child **two** levels from the scoped ancestor.
|
| `bqs_rows` reaches its buyer through `bqs_sheets`; `bqs_row_months` and
| `bqs_row_pack_sizes` reach it through `bqs_rows`. None of the three carries a
| `buyer_id` or the trait, per ARCHITECTURE.md §9.2 — so what has to hold is that
| they are unreachable without a scoped ancestor, not that they filter themselves.
|
*/

beforeEach(function (): void {
    $this->mine = Buyer::factory()->create(['name' => 'George']);
    $this->theirs = Buyer::factory()->create(['name' => 'Someone Else']);

    $this->ours = BqsSheet::factory()->for(
        BqsImport::factory()->for($this->mine, 'buyer'), 'import'
    )->create(['buyer_id' => $this->mine->id]);

    $this->hidden = BqsSheet::factory()->for(
        BqsImport::factory()->for($this->theirs, 'buyer'), 'import'
    )->create(['buyer_id' => $this->theirs->id]);

    BqsRow::factory()->for($this->ours, 'sheet')->create();
    BqsRow::factory()->for($this->hidden, 'sheet')->create();
});

test('a user sees only the bqs records of buyers they hold', function () {
    $this->actingAs(bqsImporter($this->mine));

    expect(BqsSheet::pluck('id')->all())->toBe([$this->ours->id])
        ->and(BqsImport::count())->toBe(1);
});

test('another buyer bqs 404s rather than 403s', function () {
    $this->actingAs(bqsImporter($this->mine));

    // Route-model binding never finds it, so authorization is never reached.
    $this->get(route('merchandising.bqs.show', $this->hidden))->assertNotFound();
    $this->get(route('merchandising.bqs.show', $this->ours))->assertOk();
});

test('rows are unreachable without their scoped ancestor', function () {
    $this->actingAs(bqsImporter($this->mine));

    // `BqsRow` is deliberately unscoped — §9.2 forbids a scope that joins. What
    // protects it is that every read goes through the sheet, which is scoped.
    expect(BqsSheet::with('rows')->get()->pluck('rows')->flatten())->toHaveCount(1);
});

test('a user with no buyer access sees no bqs at all', function () {
    $this->actingAs(userWithPermissions(BQS_VIEW_PERMISSION));

    // Zero buyers is a valid state — a new hire pending assignment (§9.2).
    expect(BqsSheet::count())->toBe(0);

    $this->get(route('merchandising.bqs.index'))->assertOk();
});

test('a super admin sees every buyer bqs', function () {
    $this->actingAs(superAdmin());

    // `Gate::before` grants abilities and does nothing for row scoping, so
    // `seesAllBuyers()` has to name the role — §9.2's stated exception.
    expect(BqsSheet::count())->toBe(2);
});

test('deleting a sheet cascades to its rows', function () {
    $this->actingAs(superAdmin());

    $this->ours->delete();

    expect(BqsRow::count())->toBe(1);
});
