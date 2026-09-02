<?php

use App\Enums\Merchandising\PoConflictDecision;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Deciding what to do with an order that is already on file
|--------------------------------------------------------------------------
|
| The parser cannot tell a genuine Walmart reissue from someone re-uploading a
| stale document, so it asks. These tests cover the three answers, the
| permission that gates the destructive one, and the JSON round trip the staging
| depends on.
|
| Every test starts from a real double import — the fixture, then the same
| document with one value changed — so the staged rows are the parser's own and
| not a fixture of their own invention.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'Walmart']);
});

/**
 * Import the fixture, then a reissue of it, leaving three staged conflicts.
 *
 * Returns the pending import. The acting user is left signed in.
 */
function poPendingImport(Buyer $buyer, string ...$permissions): PoImport
{
    test()->actingAs(poImporter($buyer, ...$permissions));

    foreach ([poUpload(), poReissuedUpload()] as $file) {
        test()->post(route('merchandising.purchase-orders.import.store'), [
            'buyer_id' => $buyer->id,
            'file' => $file,
        ]);
    }

    return PoImport::query()->pending()->sole();
}

/** Post a decision for every conflict the import holds. */
function poResolve(PoImport $import, PoConflictDecision $decision)
{
    $decisions = [];

    foreach ($import->staged_orders as $staged) {
        $decisions[$staged['po_number']] = $decision->value;
    }

    return test()->post(
        route('merchandising.purchase-orders.import.resolve', $import),
        ['decisions' => $decisions],
    );
}

test('revising keeps the order already held and adds the next revision', function () {
    $import = poPendingImport($this->buyer);

    $response = poResolve($import, PoConflictDecision::Revise);

    assertToast($response, 'success');

    $revisions = PurchaseOrder::where('po_number', '1000000001')
        ->orderBy('revision_no')
        ->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->revision_no)->toBe(1)
        ->and($revisions[0]->is_current)->toBeFalse()
        ->and($revisions[1]->revision_no)->toBe(2)
        ->and($revisions[1]->is_current)->toBeTrue();

    // Only the newest shows on the default list view.
    expect(PurchaseOrder::query()->current()->where('po_number', '1000000001')->count())->toBe(1);

    // The staging is cleared, or the same questions come back on the next visit.
    expect($import->fresh()->staged_orders)->toBeNull();
});

test('overwriting replaces the current revision without moving the count', function () {
    $import = poPendingImport($this->buyer, PO_IMPORT_PERMISSION, PO_VIEW_PERMISSION, PO_DELETE_PERMISSION);

    $held = PurchaseOrder::where('po_number', '1000000001')->sole();
    $heldLineIds = $held->lineItems()->pluck('id')->all();

    $response = poResolve($import, PoConflictDecision::Overwrite);

    /*
     * `warning`, not `success`: this destroyed a stored order and the message has
     * to say so — ARCHITECTURE.md §8.8.
     */
    assertToast($response, 'warning');

    $after = PurchaseOrder::where('po_number', '1000000001')->sole();

    expect($after->id)->toBe($held->id)
        // The count never lies about how many times Walmart reissued the order.
        ->and($after->revision_no)->toBe(1)
        ->and($after->is_current)->toBeTrue()
        ->and($after->source_hash)->not->toBe($held->source_hash)
        ->and($after->po_import_id)->toBe($import->id);

    // The line items were rebuilt, not reconciled — they have no identity of
    // their own beyond the order that owns them.
    expect($after->lineItems()->count())->toBe(20)
        ->and(PoLineItem::whereIn('id', $heldLineIds)->count())->toBe(0);

    expect(PoLineItem::count())->toBe(60);
});

test('overwriting leaves earlier revisions alone', function () {
    $import = poPendingImport($this->buyer, PO_IMPORT_PERMISSION, PO_VIEW_PERMISSION, PO_DELETE_PERMISSION);

    /*
     * Turn the held order into revision 2 of a pair, so there is history to lose.
     * It has to move off revision 1 before the earlier row can take that number —
     * `UNIQUE (buyer_id, po_number, revision_no)` is enforced, not advisory.
     */
    $held = PurchaseOrder::where('po_number', '1000000001')->sole();
    $held->update(['revision_no' => 2]);

    $earlier = PurchaseOrder::factory()->create([
        'buyer_id' => $this->buyer->id,
        'po_number' => '1000000001',
        'revision_no' => 1,
        'is_current' => false,
    ]);

    poResolve($import, PoConflictDecision::Overwrite);

    expect($earlier->fresh()->source_hash)->toBe($earlier->source_hash)
        ->and($earlier->fresh()->is_current)->toBeFalse()
        ->and(PurchaseOrder::where('po_number', '1000000001')->count())->toBe(2);
});

test('skipping changes nothing and still clears the staging', function () {
    $import = poPendingImport($this->buyer);

    $before = PurchaseOrder::orderBy('id')->pluck('source_hash')->all();

    $response = poResolve($import, PoConflictDecision::Skip);

    assertToast($response, 'info');

    expect(PurchaseOrder::orderBy('id')->pluck('source_hash')->all())->toBe($before)
        ->and(PurchaseOrder::count())->toBe(3)
        ->and($import->fresh()->staged_orders)->toBeNull();
});

test('sending no decisions at all is the same as skipping every one', function () {
    $import = poPendingImport($this->buyer);

    // What the dialog's "Skip all" does: the server's default is Skip, so
    // discarding and answering-skip are deliberately one code path.
    $response = $this->post(route('merchandising.purchase-orders.import.resolve', $import));

    assertToast($response, 'info');

    expect(PurchaseOrder::count())->toBe(3)
        ->and($import->fresh()->staged_orders)->toBeNull();
});

test('overwriting is refused without the delete permission', function () {
    $import = poPendingImport($this->buyer);

    // `import` lets you add orders. Destroying one is a separately granted power,
    // the same split that keeps `admin.users.assign-roles` apart from `update`.
    poResolve($import, PoConflictDecision::Overwrite)->assertForbidden();

    expect(PurchaseOrder::count())->toBe(3)
        ->and($import->fresh()->staged_orders)->toHaveCount(3);
});

test('a staged order writes exactly what a direct import would have written', function () {
    /*
     * The staged rows go through JSON on their way to `po_imports.staged_orders`
     * and come back through a different code path from a first import. Enum cases
     * and dates are what a round trip would quietly change, so they are named
     * rather than counted.
     *
     * The comparison is the *same document* down both paths: a second buyer takes
     * the reissue as a clean first import, while the first buyer reaches it as a
     * staged conflict. Comparing against the original document instead would only
     * prove that the reissue differs from it, which is the point of the fixture.
     */
    $other = Buyer::factory()->create(['name' => 'Second Buyer']);

    // Both buyers are held before acting, so `BuyerScope` sees the same set the
    // whole way through.
    $user = poImporter($this->buyer);
    $user->buyers()->attach($other);
    $this->actingAs($user);

    foreach ([[$this->buyer, poUpload()], [$this->buyer, poReissuedUpload()], [$other, poReissuedUpload()]] as [$buyer, $file]) {
        $this->post(route('merchandising.purchase-orders.import.store'), [
            'buyer_id' => $buyer->id,
            'file' => $file,
        ]);
    }

    $import = PoImport::query()->pending()->sole();

    $direct = PurchaseOrder::where('buyer_id', $other->id)
        ->where('po_number', '1000000002')
        ->sole();

    poResolve($import, PoConflictDecision::Revise);

    $resolved = PurchaseOrder::where('buyer_id', $this->buyer->id)
        ->where('po_number', '1000000002')
        ->where('revision_no', 2)
        ->sole();

    foreach (['po_type', 'parse_status', 'document_status', 'currency', 'vendor_name', 'factory_id'] as $column) {
        expect($resolved->{$column})->toEqual($direct->{$column});
    }

    expect($resolved->revised_at?->toDateTimeString())->toBe($direct->revised_at?->toDateTimeString())
        ->and($resolved->vendor_ship_date?->toDateString())->toBe($direct->vendor_ship_date?->toDateString())
        ->and($resolved->total_qty)->toBe($direct->total_qty)
        ->and($resolved->payload)->toEqual($direct->payload)
        ->and($resolved->lineItems()->count())->toBe($direct->lineItems()->count());
});

test('another user cannot answer for an import they did not upload', function () {
    $import = poPendingImport($this->buyer);

    // Buyer scope already hides another buyer's import; this narrows it further to
    // the person who chose the file, who is the only one who has seen it.
    $this->actingAs(poImporter($this->buyer));

    poResolve($import, PoConflictDecision::Revise)->assertNotFound();

    expect($import->fresh()->staged_orders)->toHaveCount(3);
});

test('an import for a buyer the user cannot see is invisible', function () {
    $import = poPendingImport($this->buyer);

    $outsider = poImporter(Buyer::factory()->create(), PO_IMPORT_PERMISSION, PO_VIEW_PERMISSION);
    $this->actingAs($outsider);

    // BuyerScope, ARCHITECTURE.md §9.2 — 404 at binding, before authorization.
    poResolve($import, PoConflictDecision::Revise)->assertNotFound();
});

test('a decision that is not one of the three is rejected', function () {
    $import = poPendingImport($this->buyer);

    $this->post(route('merchandising.purchase-orders.import.resolve', $import), [
        'decisions' => ['1000000001' => 'delete-everything'],
    ])->assertSessionHasErrors('decisions.1000000001');

    expect($import->fresh()->staged_orders)->toHaveCount(3);
});

test('a decision naming an order that is not staged is ignored, not obeyed', function () {
    $import = poPendingImport($this->buyer);

    /*
     * The staged orders on the server decide what is applied, never the shape of
     * the submitted form — so a stale tab cannot write an order that is no longer
     * waiting, and cannot invent one that never was.
     */
    $this->post(route('merchandising.purchase-orders.import.resolve', $import), [
        'decisions' => ['9999999999' => PoConflictDecision::Revise->value],
    ]);

    expect(PurchaseOrder::where('po_number', '9999999999')->count())->toBe(0)
        ->and(PurchaseOrder::count())->toBe(3);
});
