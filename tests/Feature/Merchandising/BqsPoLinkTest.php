<?php

use App\Enums\Merchandising\BqsConflictDecision;
use App\Enums\Merchandising\BqsLinkSource;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\BqsColourLink;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Services\Merchandising\BqsPoLinker;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Linking purchase-order lines to the BQS rows that planned them
|--------------------------------------------------------------------------
|
| Both fixtures describe the same garment, which is what makes every case here
| provable rather than illustrative. Measured from the documents:
|
|   BQS   6 rows: 2 x GRS74064GX, 4 x GRS74064GW
|   PO    3 orders x 4 packs x 5 sizes = 60 lines, 4 colours, all GRS74064GW
|
|   PINK-CANDY PINK   family PINK   + CANDY PINK   -> BQS row      MATCHES
|   LTBLUE-BALLAD B   family LTBLUE + BALLAD BLUE  -> truncated,   NO MATCH
|   NATURL-SANDSHEL   family NATURL + SANDSHELL    -> truncated,   NO MATCH
|   TEAL-ICY MORN     no BQS row exists at all
|
| Every colour orders 14 x 393 = 5,502 units per order, which is exactly the
| BQS rows' Initial Set Units / Store.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'George']);

    $this->user = userWithPermissions(
        BQS_IMPORT_PERMISSION,
        BQS_VIEW_PERMISSION,
        BQS_DELETE_PERMISSION,
        PO_IMPORT_PERMISSION,
        PO_VIEW_PERMISSION,
        'merchandising.purchase-orders.update',
    );
    $this->user->buyers()->attach($this->buyer);

    $this->actingAs($this->user);
});

/** Import the BQS workbook for the acting user's buyer. */
function importBqs(?string $date = null): void
{
    test()->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => test()->buyer->id,
        'bqs_date' => $date ?? '2026-09-01',
        'file' => bqsUpload(),
    ]);
}

/** Import the purchase-order document for the acting user's buyer. */
function importPo(): void
{
    test()->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => test()->buyer->id,
        'file' => poUpload(),
    ]);
}

/** Every line of one colour, across every imported order. */
function linesOfColour(string $color)
{
    return PoLineItem::query()->where('color', $color)->get();
}

test('an exactly matching colour links itself', function () {
    importBqs();
    importPo();

    $lines = linesOfColour('PINK-CANDY PINK');
    $planned = BqsRow::where('pantone_colour', 'CANDY PINK')->sole();

    // 3 orders x 5 sizes, every one of them pointed at the same BQS row.
    expect($lines)->toHaveCount(15)
        ->and($lines->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id])
        ->and($lines->first()->bqs_link_source)->toBe(BqsLinkSource::Auto);
});

test('a truncated colour does not link itself', function () {
    importBqs();
    importPo();

    // `BALLAD BLUE` cannot fit Walmart's 15-character column, so the PO says
    // `LTBLUE-BALLAD B`. Under strict equality that is not a match, and the owner
    // confirmed the rule knowing this. Widening it to a prefix match must fail here.
    expect(linesOfColour('LTBLUE-BALLAD B')->pluck('bqs_row_id')->unique()->all())->toBe([null])
        ->and(linesOfColour('NATURL-SANDSHEL')->pluck('bqs_row_id')->unique()->all())->toBe([null]);
});

test('a colour with no bqs row stays unlinked and is offered nothing', function () {
    importBqs();
    importPo();

    // `TEAL-ICY MORN` is on the order and absent from the plan. Unlinked is the
    // correct, permanent answer — not something a person should be invited to fix.
    expect(linesOfColour('TEAL-ICY MORN')->pluck('bqs_row_id')->unique()->all())->toBe([null]);

    $order = PurchaseOrder::first();

    $candidates = app(BqsPoLinker::class)->candidatesFor($order, 'GRS74064GW', 'TEAL-ICY MORN');

    // Four BQS rows share the style, so the picker is not empty — but none of them is
    // ICY MORN, which is the honest state.
    expect(collect($candidates)->pluck('label'))->not->toContain('ICY MORN');
});

test('importing the purchase order before the bqs links just as well', function () {
    importPo();

    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([null]);

    importBqs();

    // The mirror direction. Without it, half the links would never form.
    $planned = BqsRow::where('pantone_colour', 'CANDY PINK')->sole();

    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id]);
});

test('a manual link covers every size of that colour', function () {
    importBqs();
    importPo();

    $order = PurchaseOrder::first();
    $planned = BqsRow::where('pantone_colour', 'BALLAD BLUE')->sole();

    $this->put(route('merchandising.purchase-orders.bqs-link.update', $order), [
        'vendor_stock' => 'GRS74064GW',
        'color' => 'LTBLUE-BALLAD B',
        'bqs_row_id' => $planned->id,
    ])->assertRedirect();

    // One decision, and it reaches all three orders — not just the one it was made on.
    $lines = linesOfColour('LTBLUE-BALLAD B');

    expect($lines->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id])
        ->and($lines->first()->bqs_link_source)->toBe(BqsLinkSource::Manual)
        ->and(BqsColourLink::count())->toBe(1);
});

test('a manual decision is remembered for orders imported later', function () {
    importBqs();
    importPo();

    $planned = BqsRow::where('pantone_colour', 'SANDSHELL')->sole();

    $this->put(route('merchandising.purchase-orders.bqs-link.update', PurchaseOrder::first()), [
        'vendor_stock' => 'GRS74064GW',
        'color' => 'NATURL-SANDSHEL',
        'bqs_row_id' => $planned->id,
    ]);

    // Wipe the orders and import the same document again — standing in for the next
    // purchase order to arrive carrying this colour.
    PurchaseOrder::query()->delete();
    BqsImport::query()->update(['staged_rows' => null]);
    importPo();

    // Nobody was asked a second time. With strict matching this is what stops the same
    // decision being re-made on every order forever.
    expect(linesOfColour('NATURL-SANDSHEL')->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id])
        ->and(linesOfColour('NATURL-SANDSHEL')->first()->bqs_link_source)->toBe(BqsLinkSource::Manual);
});

test('clearing a link removes the standing decision too', function () {
    importBqs();
    importPo();

    $order = PurchaseOrder::first();
    $planned = BqsRow::where('pantone_colour', 'BALLAD BLUE')->sole();

    $payload = fn (?int $rowId): array => [
        'vendor_stock' => 'GRS74064GW',
        'color' => 'LTBLUE-BALLAD B',
        'bqs_row_id' => $rowId,
    ];

    $this->put(route('merchandising.purchase-orders.bqs-link.update', $order), $payload($planned->id));
    $this->put(route('merchandising.purchase-orders.bqs-link.update', $order), $payload(null));

    expect(linesOfColour('LTBLUE-BALLAD B')->pluck('bqs_row_id')->unique()->all())->toBe([null])
        ->and(linesOfColour('LTBLUE-BALLAD B')->first()->bqs_link_source)->toBeNull()
        ->and(BqsColourLink::count())->toBe(0);
});

test('the matcher never overwrites a manual decision', function () {
    importBqs();
    importPo();

    $order = PurchaseOrder::first();
    $wrongOnPurpose = BqsRow::where('pantone_colour', 'SMOKE GREEN')->sole();

    // A colour that would otherwise auto-match, pointed somewhere else by a person.
    $this->put(route('merchandising.purchase-orders.bqs-link.update', $order), [
        'vendor_stock' => 'GRS74064GW',
        'color' => 'PINK-CANDY PINK',
        'bqs_row_id' => $wrongOnPurpose->id,
    ]);

    app(BqsPoLinker::class)->linkForPurchaseOrder($order->refresh());

    expect($order->lineItems()->where('color', 'PINK-CANDY PINK')->first()->bqs_row_id)
        ->toBe($wrongOnPurpose->id);
});

test('ordered units are the pack ratio times the carton count', function () {
    importBqs();
    importPo();

    $order = PurchaseOrder::first();
    $lines = $order->lineItems()->where('color', 'PINK-CANDY PINK')->get();

    // 3 + 4 + 4 + 2 + 1 = 14 ("14PC GR SS SKATER DRESS"), x 393 cartons.
    expect($lines->sum('quantity'))->toBe(14)
        ->and($lines->first()->total_cartons_per_line)->toBe(393)
        ->and($lines->sum(fn (PoLineItem $line): int => (int) $line->orderedUnits()))->toBe(5502);

    // Which is this order's half of the plan, to the unit — the store side of the
    // initial buy. Summing `quantity` would have reported 14.
    expect(BqsRow::where('pantone_colour', 'CANDY PINK')->sole()->initial_set_units_store)->toBe(5502);

    // The carton count is per pack and varies widely; assuming one value holds for a
    // whole order is what produced a wrong channel choice once already.
    expect(PoLineItem::query()->distinct()->pluck('total_cartons_per_line')->count())
        ->toBeGreaterThan(1);
});

test('a bqs revision carries its links forward', function () {
    importBqs();
    importPo();

    $planned = BqsRow::where('pantone_colour', 'CANDY PINK')->sole();

    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id]);

    // The same workbook under a different name, so its rows collide and it stages.
    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => '2026-10-01',
        'file' => bqsRevisedUpload(),
    ]);

    $this->post(
        route('merchandising.bqs.import.resolve', BqsImport::pending()->sole()),
        ['decision' => BqsConflictDecision::Revise->value],
    );

    $revised = BqsSheet::query()->where('revision_no', 2)->sole()
        ->rows()->where('pantone_colour', 'CANDY PINK')->sole();

    // The link moved to revision 2's row, matched on the row key.
    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([$revised->id])
        ->and($revised->id)->not->toBe($planned->id);
});

test('an overwrite carries links forward despite deleting the rows first', function () {
    importBqs();
    importPo();

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => '2026-10-01',
        'file' => bqsRevisedUpload(),
    ]);

    $this->post(
        route('merchandising.bqs.import.resolve', BqsImport::pending()->sole()),
        ['decision' => BqsConflictDecision::Overwrite->value],
    );

    $replaced = BqsRow::where('pantone_colour', 'CANDY PINK')->sole();

    /*
     * `bqs_row_id` is `nullOnDelete`, so capturing the links before the delete is the
     * only order that survives. If this ever regresses, the import still succeeds and
     * the BQS silently reports nothing ordered — which is why it is asserted.
     */
    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([$replaced->id]);
});

test('a link cannot be made across buyers', function () {
    importBqs();
    importPo();

    $theirs = Buyer::factory()->create(['name' => 'Someone Else']);
    $this->user->buyers()->attach($theirs);

    // A BQS belonging to a different buyer, which this user can nonetheless see.
    $foreign = BqsRow::factory()->for(
        BqsSheet::factory()->for(BqsImport::factory()->for($theirs, 'buyer'), 'import')
            ->create(['buyer_id' => $theirs->id]),
        'sheet',
    )->create();

    // Neither table carries a `buyer_id`, so nothing in the database refuses this —
    // the guard is the request and the linker, and this is what proves it.
    $this->put(route('merchandising.purchase-orders.bqs-link.update', PurchaseOrder::first()), [
        'vendor_stock' => 'GRS74064GW',
        'color' => 'LTBLUE-BALLAD B',
        'bqs_row_id' => $foreign->id,
    ])->assertSessionHasErrors('bqs_row_id');

    expect(linesOfColour('LTBLUE-BALLAD B')->pluck('bqs_row_id')->unique()->all())->toBe([null]);
});

test('an ambiguous colour is left unlinked rather than guessed', function () {
    importBqs();

    // A second current BQS row with the same style and the same colour. Real enough:
    // two seasons' plans can carry one colourway of one style.
    $original = BqsRow::where('pantone_colour', 'CANDY PINK')->sole();

    BqsRow::factory()->for(
        BqsSheet::factory()->for(BqsImport::factory()->for($this->buyer, 'buyer'), 'import')
            ->create(['buyer_id' => $this->buyer->id]),
        'sheet',
    )->create([
        'vendor_style_no' => $original->vendor_style_no,
        'colour_family' => $original->colour_family,
        'pantone_colour' => $original->pantone_colour,
    ]);

    importPo();

    expect(linesOfColour('PINK-CANDY PINK')->pluck('bqs_row_id')->unique()->all())->toBe([null]);
});

test('the bqs detail page reports ordered against planned, split by po type', function () {
    importBqs();
    importPo();

    $sheet = BqsSheet::sole();

    $this->get(route('merchandising.bqs.show', $sheet))
        ->assertOk()
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['rows']);

            $candyPink = $rows->firstWhere('pantone_colour', 'CANDY PINK');
            $insignia = $rows->firstWhere('pantone_colour', 'INSIGNIA BLUE');

            /*
             * The document holds three orders and they reconcile to the plan exactly:
             *
             *   type 43   5,502 (store) + 266 (ecomm) = 5,768 = Initial / OMNI
             *   type 42                       21,868          = Replen  / OMNI
             *
             * Ecomm arrives as its own purchase order, which is why the comparison is
             * against OMNI. Against Store this would read 105%.
             */
            expect($candyPink['ordered']['initial'])->toBe(5768)
                ->and($candyPink['ordered']['replen'])->toBe(21868)
                ->and($candyPink['ordered']['other'])->toBe(0)
                ->and($candyPink['ordered']['po_numbers'])->toHaveCount(3)
                // Both halves complete, to the unit.
                ->and($candyPink['initial_set_units_omni'])->toBe(5768)
                ->and($candyPink['replenishment_units_omni'])->toBe(21868)
                // Planned but never ordered — the normal early state, not an error.
                ->and($insignia['ordered']['initial'])->toBe(0)
                ->and($insignia['ordered']['po_numbers'])->toBe([]);
        });
});

test('the purchase order detail page groups links by colour, not by line', function () {
    importBqs();
    importPo();

    $this->get(route('merchandising.purchase-orders.show', PurchaseOrder::first()))
        ->assertOk()
        ->assertInertia(function ($page) {
            $links = collect($page->toArray()['props']['colourLinks']);

            // Four colours, not sixty line items — one decision per colour.
            expect($links)->toHaveCount(4);

            $matched = $links->firstWhere('color', 'PINK-CANDY PINK');
            $truncated = $links->firstWhere('color', 'LTBLUE-BALLAD B');
            $orphan = $links->firstWhere('color', 'TEAL-ICY MORN');

            expect($matched['source'])->toBe('auto')
                ->and($matched['ordered_units'])->toBe(5502)
                ->and($truncated['bqs_row_id'])->toBeNull()
                // Four rows share the style, so a person has something to choose from.
                ->and($truncated['candidates'])->toHaveCount(4)
                ->and($orphan['bqs_row_id'])->toBeNull();
        });
});

/*
|--------------------------------------------------------------------------
| The infant order, whose colours reached the matcher as null
|--------------------------------------------------------------------------
|
| A `Single Item Pack` order prints no SIZE column on its first pack, and the
| line extractor once derived the colour from the size's position — so every
| line of every such order stored `color = null`. A null colour cannot match a
| BQS row and cannot be mapped by hand either: `BqsColourMatch::split()` returns
| null and the line simply reports as unplanned. Nothing said so.
|
| These prove the two halves separately: that a readable colour of that shape
| links, and that a null one is refused rather than guessed at.
|
*/

/** A BQS row of this buyer, for a style and colour named outright. */
function bqsRowFor(string $style, string $family, string $pantone): BqsRow
{
    return BqsRow::factory()->for(
        BqsSheet::factory()->for(BqsImport::factory()->for(test()->buyer, 'buyer'), 'import')
            ->create(['buyer_id' => test()->buyer->id]),
        'sheet',
    )->create([
        'vendor_style_no' => $style,
        'colour_family' => $family,
        'pantone_colour' => $pantone,
    ]);
}

test('an infant colour links once the parser can read it', function () {
    $planned = bqsRowFor('CDY33205IU', 'RED', 'JESTER RED');

    $order = PurchaseOrder::factory()->for($this->buyer, 'buyer')->create();

    // The five packs of the reference document: one with no size, four sized.
    foreach ([null, '3-6M', '6-12M', '12-18M', '18-24M'] as $size) {
        PoLineItem::factory()->for($order, 'purchaseOrder')->create([
            'vendor_stock' => 'CDY33205IU',
            'color' => 'RED-JESTER RED',
            'size' => $size,
        ]);
    }

    expect(app(BqsPoLinker::class)->linkForPurchaseOrder($order))->toBe(5);

    $lines = linesOfColour('RED-JESTER RED');

    // A sizeless line links exactly as a sized one does — the colour is what
    // the match is made on, and the size plays no part in it.
    expect($lines)->toHaveCount(5)
        ->and($lines->pluck('bqs_row_id')->unique()->all())->toBe([$planned->id])
        ->and($lines->firstWhere('size', null)->bqs_row_id)->toBe($planned->id)
        ->and($lines->first()->bqs_link_source)->toBe(BqsLinkSource::Auto);
});

test('a line whose colour could not be parsed is never linked to anything', function () {
    bqsRowFor('CDY33205IU', 'RED', 'JESTER RED');

    $order = PurchaseOrder::factory()->for($this->buyer, 'buyer')->create();

    PoLineItem::factory()->for($order, 'purchaseOrder')->create([
        'vendor_stock' => 'CDY33205IU',
        'color' => null,
        'size' => '3-6M',
    ]);

    // This is the state the whole fix exists to prevent, and it must stay
    // unlinkable rather than fall through to the only row of that style.
    expect(app(BqsPoLinker::class)->linkForPurchaseOrder($order))->toBe(0)
        ->and(PoLineItem::whereNull('color')->sole()->bqs_row_id)->toBeNull();
});

test('linking requires the update permission', function () {
    importBqs();
    importPo();

    $order = PurchaseOrder::first();
    $planned = BqsRow::where('pantone_colour', 'BALLAD BLUE')->sole();

    $reader = userWithPermissions(PO_VIEW_PERMISSION);
    $reader->buyers()->attach($this->buyer);

    $this->actingAs($reader)
        ->put(route('merchandising.purchase-orders.bqs-link.update', $order), [
            'vendor_stock' => 'GRS74064GW',
            'color' => 'LTBLUE-BALLAD B',
            'bqs_row_id' => $planned->id,
        ])
        ->assertForbidden();
});
