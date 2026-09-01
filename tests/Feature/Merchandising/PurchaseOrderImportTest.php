<?php

use App\Enums\Merchandising\PoParseStatus;
use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Importing a purchase-order document
|--------------------------------------------------------------------------
|
| The `.docx` fixture is used throughout because it is the only format that
| needs no external binary, which keeps this file fast. That the other two
| formats produce identical data is `PoParserTest`'s job, so nothing here has to
| repeat it.
|
| The document holds three purchase orders, four packs each, five line items per
| pack — 60 line items in all.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'Walmart']);
});

test('a guest cannot upload', function () {
    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ])->assertRedirect(route('login'));
});

test('the import permission is required, and view alone is not enough', function () {
    $this->actingAs(poImporter($this->buyer, PO_VIEW_PERMISSION));

    // `import` is deliberately separate from `view` and from `create` — running a
    // parser over an upload is its own power. See RolePermissionSeeder.
    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ])->assertForbidden();
});

test('the import dialog offers only buyers the user may see', function () {
    $mine = $this->buyer;
    $theirs = Buyer::factory()->create(['name' => 'Someone Else']);

    $this->actingAs(poImporter($mine));

    // The form is a modal on the list, so its options ride on the list page.
    $this->get(route('merchandising.purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('merchandising/purchase-orders/index')
            ->has('importBuyers', 1)
            ->where('importBuyers.0.value', $mine->id));

    expect($theirs->exists)->toBeTrue();
});

test('an inactive buyer is not offered', function () {
    $this->buyer->update(['status' => RecordStatus::Inactive]);

    $this->actingAs(poImporter($this->buyer));

    // Deactivating retires a buyer from the pickers without touching its orders —
    // ARCHITECTURE.md §9.3.1.
    $this->get(route('merchandising.purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('importBuyers', 0));
});

test('a user who cannot import pays for neither of the dialog queries', function () {
    $this->actingAs(poImporter($this->buyer, PO_VIEW_PERMISSION));

    /*
     * `production-manager` reads this list and can never import. Both props are
     * gated so the buyer lookup and the pending-import lookup never run for them.
     */
    $this->get(route('merchandising.purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('importBuyers', 0)
            ->where('pendingImport', null));
});

test('importing for a buyer the user cannot see is refused', function () {
    $theirs = Buyer::factory()->create();

    $this->actingAs(poImporter($this->buyer));

    /*
     * The guarantee behind picking the buyer on the form: an import into a buyer
     * the uploader cannot see would succeed and BuyerScope would then hide the
     * result — a success message over an empty table. This makes that unreachable.
     */
    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $theirs->id,
        'file' => poUpload(),
    ])->assertSessionHasErrors('buyer_id');

    expect(PurchaseOrder::withoutBuyerScope()->count())->toBe(0);
});

test('a document imports every purchase order it holds', function () {
    $this->actingAs(poImporter($this->buyer));

    $response = $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    $response->assertRedirect(route('merchandising.purchase-orders.index'));
    assertToast($response, 'success');

    expect(PoImport::count())->toBe(1)
        ->and(PurchaseOrder::count())->toBe(3)
        ->and(PoLineItem::count())->toBe(60);

    $order = PurchaseOrder::where('po_number', '1000000001')->firstOrFail();

    expect($order->buyer_id)->toBe($this->buyer->id)
        ->and($order->revision_no)->toBe(1)
        ->and($order->is_current)->toBeTrue()
        ->and($order->parse_status)->toBe(PoParseStatus::Success)
        ->and($order->document_status)->toBe('ACTIVE')
        ->and($order->quote_id)->toBe('90000001')
        ->and($order->revised_by)->not->toBeNull()
        ->and($order->lineItems()->count())->toBe(20)
        // The header is columns; everything else rides in the payload.
        ->and($order->payload)->toHaveKeys(['header', 'addresses', 'logistics', 'tariffs', 'packs']);
});

test('the uploader is stamped as the actor', function () {
    $user = poImporter($this->buyer);
    $this->actingAs($user);

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    // ActorObserver, ARCHITECTURE.md §9.3 — neither column is mass assignable.
    expect(PurchaseOrder::first()->inserted_by)->toBe($user->id)
        ->and(PoImport::first()->inserted_by)->toBe($user->id);
});

test('the uploaded document is retained beside the import', function () {
    $this->actingAs(poImporter($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    // What makes a failed import diagnosable later rather than merely reported.
    $import = PoImport::firstOrFail();

    expect($import->stored_path)->not->toBeNull();
    Storage::disk('local')->assertExists($import->stored_path);
});

test('re-importing the identical document is refused, and writes nothing', function () {
    $this->actingAs(poImporter($this->buyer));

    $payload = fn (): array => ['buyer_id' => $this->buyer->id, 'file' => poUpload()];

    $this->post(route('merchandising.purchase-orders.import.store'), $payload());

    $second = $this->post(route('merchandising.purchase-orders.import.store'), $payload());

    /*
     * `warning`, not `error`: the actor can clear this themselves by deleting what
     * is already there — ARCHITECTURE.md §8.8.
     */
    assertToast($second, 'warning');

    expect(PurchaseOrder::count())->toBe(3)
        ->and(PoLineItem::count())->toBe(60);
});

test('a reissued document is staged, not written, and waits for a decision', function () {
    $this->actingAs(poImporter($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    $response = $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poReissuedUpload(),
    ]);

    /*
     * The parser cannot tell a genuine Walmart reissue from someone re-uploading a
     * stale document — both are the same order number with different content. So
     * nothing is written until the uploader says which it is.
     */
    assertToast($response, 'warning');

    expect(PurchaseOrder::where('po_number', '1000000001')->count())->toBe(1)
        ->and(PurchaseOrder::where('po_number', '1000000001')->first()->revision_no)->toBe(1);

    $pending = PoImport::query()->pending()->sole();

    expect($pending->staged_orders)->toHaveCount(3)
        ->and(array_column($pending->staged_orders, 'po_number'))
        ->toContain('1000000001');
});

test('the pending import is offered back on the list, with both sides of each conflict', function () {
    $this->actingAs(poImporter($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poReissuedUpload(),
    ]);

    $this->get(route('merchandising.purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pendingImport.source_file_name', 'PO-SAMPLE-WALMART-REV.docx')
            ->has('pendingImport.conflicts', 3)
            // What makes the question answerable: a PO number alone cannot tell a
            // reissue from a stale re-upload.
            ->has('pendingImport.conflicts.0.held.revision_no')
            ->has('pendingImport.conflicts.0.incoming.line_item_count')
            // The rows to be written stay on the server; the browser sends back a
            // decision per order, not data.
            ->missing('pendingImport.conflicts.0.attributes')
            ->missing('pendingImport.conflicts.0.line_items'));
});

test('a colliding document still imports the orders that collide with nothing', function () {
    $this->actingAs(poImporter($this->buyer));

    // One order already held, so only that one collides on the second upload.
    PurchaseOrder::factory()->create([
        'buyer_id' => $this->buyer->id,
        'po_number' => '1000000001',
    ]);

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    /*
     * Holding back all three because one was already on file would lose two good
     * orders to protect nothing — merchandising.md §3.4.
     */
    expect(PurchaseOrder::where('po_number', '1000000002')->count())->toBe(1)
        ->and(PurchaseOrder::where('po_number', '1000000003')->count())->toBe(1)
        ->and(PurchaseOrder::where('po_number', '1000000001')->count())->toBe(1);

    expect(PoImport::query()->pending()->sole()->staged_orders)->toHaveCount(1);
});

test('a file that is not a purchase-order document is reported, not stored', function () {
    $this->actingAs(poImporter($this->buyer));

    $path = tempnam(sys_get_temp_dir(), 'po').'.pdf';
    file_put_contents($path, '%PDF-1.4 not really a pdf');

    $response = $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => new UploadedFile($path, 'nonsense.pdf', null, null, true),
    ]);

    // `error`: no amount of work on other records makes this file readable.
    assertToast($response, 'error');

    expect(PoImport::count())->toBe(0)
        ->and(PurchaseOrder::count())->toBe(0);

    // A rejected upload leaves no orphan behind.
    expect(Storage::disk('local')->allFiles('po-imports'))->toBe([]);

    @unlink($path);
});

test('an oversized or wrong-typed file never reaches the parser', function () {
    $this->actingAs(poImporter($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('file');

    expect(PoImport::count())->toBe(0);
});

test('failed orders are stored but excluded from the usable set', function () {
    /*
     * Decision: everything the parser produces is persisted, failures included, so
     * their warnings stay next to the document. The hazard that creates — a table
     * holding rows known to be wrong — is mitigated by `scopeUsable()`, and this
     * pins it. A genuinely unparseable document is not needed to prove the scope,
     * and crafting one would test the fixture rather than the guard.
     */
    $good = PurchaseOrder::factory()->create(['buyer_id' => $this->buyer->id]);
    $bad = PurchaseOrder::factory()->failed()->create(['buyer_id' => $this->buyer->id]);

    expect(PurchaseOrder::query()->usable()->pluck('id')->all())->toBe([$good->id])
        ->and($bad->parse_status)->toBe(PoParseStatus::Failed);
});

test('the list separates current orders, revisions and failures', function () {
    $this->actingAs(poImporter($this->buyer));

    PurchaseOrder::factory()->create(['buyer_id' => $this->buyer->id]);
    PurchaseOrder::factory()->superseded()->create(['buyer_id' => $this->buyer->id]);
    PurchaseOrder::factory()->failed()->create(['buyer_id' => $this->buyer->id]);

    // `view` chooses the record set and lives in the toolbar, not the filter row —
    // ARCHITECTURE.md §8.6.
    $this->get(route('merchandising.purchase-orders.index'))
        ->assertInertia(fn ($page) => $page->has('purchaseOrders.data', 1));

    $this->get(route('merchandising.purchase-orders.index', ['view' => 'revisions']))
        ->assertInertia(fn ($page) => $page->has('purchaseOrders.data', 2));

    $this->get(route('merchandising.purchase-orders.index', ['view' => 'failed']))
        ->assertInertia(fn ($page) => $page->has('purchaseOrders.data', 1));
});

test('one order can be opened in full', function () {
    $this->actingAs(poImporter($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    $order = PurchaseOrder::where('po_number', '1000000002')->firstOrFail();

    $this->get(route('merchandising.purchase-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('merchandising/purchase-orders/show')
            ->where('purchaseOrder.po_number', '1000000002')
            ->where('purchaseOrder.source_file_name', 'PO-SAMPLE-WALMART.docx')
            ->has('purchaseOrder.payload.packs', 4));
});
