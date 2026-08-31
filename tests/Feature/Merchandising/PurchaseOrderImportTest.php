<?php

use App\Enums\Merchandising\PoParseStatus;
use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\User;
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

const IMPORT_PERMISSION = 'merchandising.purchase-orders.import';

const VIEW_PERMISSION = 'merchandising.purchase-orders.view';

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'Walmart']);
});

/** The redacted fixture, as a real upload. */
function upload(string $extension = 'docx'): UploadedFile
{
    $path = __DIR__.'/../../Fixtures/Merchandising/PO-SAMPLE-WALMART.'.$extension;

    return new UploadedFile($path, 'PO-SAMPLE-WALMART.'.$extension, null, null, true);
}

/**
 * The same document with one value altered, so it parses to the same purchase
 * orders with different content — which is what a genuine Walmart reissue is.
 *
 * The replacement is the **same length** as what it replaces: the parser reads
 * fixed-width columns, so a longer factory name would shift the block and change
 * far more than intended.
 */
function reissuedUpload(): UploadedFile
{
    $source = __DIR__.'/../../Fixtures/Merchandising/PO-SAMPLE-WALMART.docx';
    $target = tempnam(sys_get_temp_dir(), 'po').'.docx';

    copy($source, $target);

    $zip = new ZipArchive;
    $zip->open($target);
    $xml = $zip->getFromName('word/document.xml');
    $zip->addFromString('word/document.xml', str_replace('SAMPLERY', 'SAMPLERZ', $xml));
    $zip->close();

    return new UploadedFile($target, 'PO-SAMPLE-WALMART-REV.docx', null, null, true);
}

/** A user who may import, and who holds the buyer being imported for. */
function importer(Buyer $buyer, string ...$permissions): User
{
    $user = userWithPermissions(...($permissions ?: [IMPORT_PERMISSION, VIEW_PERMISSION]));
    $user->buyers()->attach($buyer);

    return $user;
}

test('a guest cannot reach the import form', function () {
    $this->get(route('merchandising.purchase-orders.import.create'))
        ->assertRedirect(route('login'));
});

test('the import permission is required, and view alone is not enough', function () {
    $this->actingAs(importer($this->buyer, VIEW_PERMISSION));

    // `import` is deliberately separate from `view` and from `create` — running a
    // parser over an upload is its own power. See RolePermissionSeeder.
    $this->get(route('merchandising.purchase-orders.import.create'))->assertForbidden();

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
    ])->assertForbidden();
});

test('the import form offers only buyers the user may see', function () {
    $mine = $this->buyer;
    $theirs = Buyer::factory()->create(['name' => 'Someone Else']);

    $this->actingAs(importer($mine));

    $this->get(route('merchandising.purchase-orders.import.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('merchandising/purchase-orders/import')
            ->has('buyers', 1)
            ->where('buyers.0.value', $mine->id));

    expect($theirs->exists)->toBeTrue();
});

test('an inactive buyer is not offered', function () {
    $this->buyer->update(['status' => RecordStatus::Inactive]);

    $this->actingAs(importer($this->buyer));

    // Deactivating retires a buyer from the pickers without touching its orders —
    // ARCHITECTURE.md §9.3.1.
    $this->get(route('merchandising.purchase-orders.import.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('buyers', 0));
});

test('importing for a buyer the user cannot see is refused', function () {
    $theirs = Buyer::factory()->create();

    $this->actingAs(importer($this->buyer));

    /*
     * The guarantee behind picking the buyer on the form: an import into a buyer
     * the uploader cannot see would succeed and BuyerScope would then hide the
     * result — a success message over an empty table. This makes that unreachable.
     */
    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $theirs->id,
        'file' => upload(),
    ])->assertSessionHasErrors('buyer_id');

    expect(PurchaseOrder::withoutBuyerScope()->count())->toBe(0);
});

test('a document imports every purchase order it holds', function () {
    $this->actingAs(importer($this->buyer));

    $response = $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
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
    $user = importer($this->buyer);
    $this->actingAs($user);

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
    ]);

    // ActorObserver, ARCHITECTURE.md §9.3 — neither column is mass assignable.
    expect(PurchaseOrder::first()->inserted_by)->toBe($user->id)
        ->and(PoImport::first()->inserted_by)->toBe($user->id);
});

test('the uploaded document is retained beside the import', function () {
    $this->actingAs(importer($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
    ]);

    // What makes a failed import diagnosable later rather than merely reported.
    $import = PoImport::firstOrFail();

    expect($import->stored_path)->not->toBeNull();
    Storage::disk('local')->assertExists($import->stored_path);
});

test('re-importing the identical document is refused, and writes nothing', function () {
    $this->actingAs(importer($this->buyer));

    $payload = fn (): array => ['buyer_id' => $this->buyer->id, 'file' => upload()];

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

test('a reissued document is stored as a new revision and supersedes the old one', function () {
    $this->actingAs(importer($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
    ]);

    $response = $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => reissuedUpload(),
    ]);

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
});

test('a file that is not a purchase-order document is reported, not stored', function () {
    $this->actingAs(importer($this->buyer));

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
    $this->actingAs(importer($this->buyer));

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
    $this->actingAs(importer($this->buyer));

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
    $this->actingAs(importer($this->buyer));

    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => upload(),
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
