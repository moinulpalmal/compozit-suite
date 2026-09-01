<?php

use App\Enums\Merchandising\TnaMilestone;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;
use App\Services\Merchandising\TnaCalculator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| The time-and-action board
|--------------------------------------------------------------------------
|
| Driven by the two real fixtures, because the arithmetic is only worth testing
| against dates somebody actually sent. Measured from the purchase-order
| document — these are not chosen numbers:
|
|   PO 1000000001   vendor ship date 2026-10-22
|   PO 1000000002   vendor ship date 2026-10-23
|   PO 1000000003   vendor ship date 2026-10-24
|
| Imported against a BQS dated 2026-02-01 they run 263, 264 and 265 days, and
| that one-day stagger is the entire reason templates match a band rather than a
| lead time. `a single band serves three different lead times` is the test that
| pins it; if someone re-keys the register on an exact value it fails.
|
*/

const TNA_VIEW_PERMISSION = 'merchandising.tna.view';

/** The BQS date every test measures from, chosen so the lead times are 263-265. */
const TNA_BQS_DATE = '2026-02-01';

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'George']);

    $this->user = userWithPermissions(
        BQS_IMPORT_PERMISSION,
        BQS_VIEW_PERMISSION,
        PO_IMPORT_PERMISSION,
        PO_VIEW_PERMISSION,
        TNA_VIEW_PERMISSION,
    );
    $this->user->buyers()->attach($this->buyer);

    $this->actingAs($this->user);
});

/** Import both real documents, so the orders are linked to a BQS. */
function importTnaDocuments(string $bqsDate = TNA_BQS_DATE): void
{
    test()->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => test()->buyer->id,
        'bqs_date' => $bqsDate,
        'file' => bqsUpload(),
    ]);

    test()->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => test()->buyer->id,
        'file' => poUpload(),
    ]);
}

/**
 * A template covering the reference lead times, with the two offsets and a full
 * urgency ladder.
 */
function tnaTemplateCovering(int $from = 241, int $to = 300): TnaTemplate
{
    $template = TnaTemplate::factory()
        ->covering($from, $to)
        ->withOffsets(trims: 10, productionSample: 12)
        ->create(['name' => "Band {$from}-{$to}"]);

    $template->colors()->createMany([
        ['notification_color_id' => NotificationColor::factory()->create(['name' => "Super Urgent {$from}"])->id, 'max_days_remaining' => -1],
        ['notification_color_id' => NotificationColor::factory()->create(['name' => "Urgent {$from}"])->id, 'max_days_remaining' => 7],
        ['notification_color_id' => NotificationColor::factory()->create(['name' => "Enough {$from}"])->id, 'max_days_remaining' => 21],
        ['notification_color_id' => NotificationColor::factory()->create(['name' => "Good {$from}"])->id, 'max_days_remaining' => null],
    ]);

    return $template->fresh(['milestones', 'colors.color']);
}

/** The plan for one order by number. */
function tnaPlanFor(string $poNumber)
{
    return app(TnaCalculator::class)->plan(
        PurchaseOrder::where('po_number', $poNumber)->sole(),
    );
}

test('lead time is the ship date minus the BQS date', function () {
    importTnaDocuments();

    $plan = tnaPlanFor('1000000001');

    // 2026-02-01 -> 2026-10-22. The recap sheet's own formula, =I4-D4.
    expect($plan->leadTimeDays)->toBe(263)
        ->and($plan->bqsDate?->toDateString())->toBe('2026-02-01')
        ->and($plan->shipDate?->toDateString())->toBe('2026-10-22');
});

test('a single band serves three different lead times', function () {
    importTnaDocuments();
    tnaTemplateCovering(241, 300);

    $plans = collect(['1000000001', '1000000002', '1000000003'])
        ->mapWithKeys(fn (string $number): array => [$number => tnaPlanFor($number)]);

    // The stagger that rules out keying the register on an exact lead time.
    expect($plans->map->leadTimeDays->values()->all())->toBe([263, 264, 265])
        ->and($plans->every(fn ($plan): bool => $plan->isScheduled()))->toBeTrue()
        ->and($plans->map->template->map->name->unique()->values()->all())->toBe(['Band 241-300']);
});

test('planned dates are the BQS date plus each offset', function () {
    importTnaDocuments();
    tnaTemplateCovering();

    $dates = collect(tnaPlanFor('1000000001')->milestones)
        ->pluck('date', 'milestone');

    expect($dates[TnaMilestone::TrimsApproval->value])->toBe('2026-02-11')
        ->and($dates[TnaMilestone::ProductionSampleApproval->value])->toBe('2026-02-13')
        // Shipment is the order's own date, never an offset.
        ->and($dates[TnaMilestone::Shipment->value])->toBe('2026-10-22');
});

test('a template omitting a milestone leaves that column empty rather than scheduling it on the BQS date', function () {
    importTnaDocuments();

    $template = TnaTemplate::factory()->covering(241, 300)->create();
    $template->milestones()->create([
        'milestone' => TnaMilestone::TrimsApproval,
        'offset_days' => 10,
    ]);

    $dates = collect(tnaPlanFor('1000000001')->milestones)->pluck('date', 'milestone');

    expect($dates[TnaMilestone::TrimsApproval->value])->toBe('2026-02-11')
        ->and($dates[TnaMilestone::ProductionSampleApproval->value])->toBeNull();
});

test('an uncovered lead time still reports the lead time, and says why there are no dates', function () {
    importTnaDocuments();
    tnaTemplateCovering(60, 90);

    $plan = tnaPlanFor('1000000001');

    expect($plan->isScheduled())->toBeFalse()
        ->and($plan->leadTimeDays)->toBe(263)
        ->and($plan->reason)->toContain('263')
        // The shipment date is known from the order even with no template.
        ->and($plan->milestones)->toHaveCount(1)
        ->and($plan->milestones[0]['date'])->toBe('2026-10-22')
        ->and($plan->milestones[0]['color'])->toBeNull();
});

test('an inactive band matches nothing', function () {
    importTnaDocuments();

    TnaTemplate::factory()->covering(241, 300)->withOffsets()->inactive()->create();

    expect(tnaPlanFor('1000000001')->isScheduled())->toBeFalse();
});

test('an order with no BQS link says so rather than showing blank cells', function () {
    // The purchase order alone: nothing to link against.
    $this->post(route('merchandising.purchase-orders.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => poUpload(),
    ]);

    tnaTemplateCovering();

    $plan = tnaPlanFor('1000000001');

    expect($plan->bqsDate)->toBeNull()
        ->and($plan->leadTimeDays)->toBeNull()
        ->and($plan->reason)->toContain('linked to a BQS row');
});

test('an order linked to two BQS sheets is refused rather than resolved to one of them', function () {
    importTnaDocuments();
    tnaTemplateCovering();

    $order = PurchaseOrder::where('po_number', '1000000001')->sole();

    /*
     * Point one line at a row on a second sheet. Nothing in the database forbids
     * this — neither table carries a buyer_id and the linker is the only guard —
     * so the calculator has to notice it rather than average the two dates.
     */
    $other = BqsRow::factory()->for(
        BqsSheet::factory()->create([
            'buyer_id' => $this->buyer->id,
            'bqs_date' => '2026-03-01',
        ]),
        'sheet',
    )->create();

    PoLineItem::where('purchase_order_id', $order->id)
        ->whereNotNull('bqs_row_id')
        ->first()
        ->forceFill(['bqs_row_id' => $other->id])
        ->save();

    $plan = tnaPlanFor('1000000001');

    expect($plan->bqsDate)->toBeNull()
        ->and($plan->leadTimeDays)->toBeNull()
        ->and($plan->reason)->toContain('2 different BQS sheets');
});

test('a ship date on or before the BQS date is refused as a data error', function () {
    // A BQS dated after every ship date in the document.
    importTnaDocuments('2027-01-01');
    tnaTemplateCovering(1, 65535);

    $plan = tnaPlanFor('1000000001');

    expect($plan->leadTimeDays)->toBeLessThan(0)
        ->and($plan->isScheduled())->toBeFalse()
        ->and($plan->reason)->toContain('not after the BQS date');
});

test('a milestone is coloured by how many days remain', function (int $daysBeforeShip, string $expected) {
    importTnaDocuments();
    $template = tnaTemplateCovering();

    // Freeze today relative to the trims-approval date, 2026-02-11.
    $this->travelTo(Carbon::parse('2026-02-11')->subDays($daysBeforeShip));

    $trims = collect(tnaPlanFor('1000000001')->milestones)
        ->firstWhere('milestone', TnaMilestone::TrimsApproval->value);

    expect($trims['days_remaining'])->toBe($daysBeforeShip)
        ->and($trims['color']['name'])->toBe($expected.' '.$template->lead_time_from);
})->with([
    'overdue by a day' => [-1, 'Super Urgent'],
    'due today' => [0, 'Urgent'],
    'the last urgent day' => [7, 'Urgent'],
    'just past urgent' => [8, 'Enough'],
    'the last comfortable day' => [21, 'Enough'],
    'plenty of time' => [22, 'Good'],
]);

test('a ladder with no catch-all leaves distant dates uncoloured rather than guessing', function () {
    importTnaDocuments();

    $template = TnaTemplate::factory()->covering(241, 300)->withOffsets()->create();
    $template->colors()->create([
        'notification_color_id' => NotificationColor::factory()->create()->id,
        'max_days_remaining' => 7,
    ]);

    $this->travelTo(Carbon::parse('2026-01-01'));

    $trims = collect(tnaPlanFor('1000000001')->milestones)
        ->firstWhere('milestone', TnaMilestone::TrimsApproval->value);

    expect($trims['date'])->toBe('2026-02-11')
        ->and($trims['color'])->toBeNull();
});

test('the board lists current orders with their schedules', function () {
    importTnaDocuments();
    tnaTemplateCovering();

    $this->get(route('merchandising.tna.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('merchandising/tna/index')
            ->has('orders.data', 3)
            ->where('orders.data.0.tna.lead_time_days', 263)
            ->has('orders.data.0.tna.milestones', 3)
            ->where('orders.data.0.tna.reason', null));
});

test('the page costs the same number of queries however many orders it holds', function () {
    importTnaDocuments();
    tnaTemplateCovering();

    $calculator = app(TnaCalculator::class);
    $orders = PurchaseOrder::query()->current()->usable()->get();

    expect($orders)->toHaveCount(3);

    // One listener for both measurements: registering a second would double-count.
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $count = function (EloquentCollection $subject) use ($calculator, &$queries): int {
        $queries = 0;

        $calculator->plans($subject);

        return $queries;
    };

    /*
     * The invariant is that the cost does not grow with the page, not that it is
     * any particular number — the register is loaded once and the BQS dates come
     * back in one grouped query, so three orders cost what one does. A loop calling
     * plan() per row would make this ratio 3:1.
     */
    expect($count($orders->take(1)))->toBe($count($orders));
});

test('the board is gated on its own permission', function () {
    $stranger = userWithPermissions(PO_VIEW_PERMISSION);
    $stranger->buyers()->attach($this->buyer);

    $this->actingAs($stranger)
        ->get(route('merchandising.tna.index'))
        ->assertForbidden();
});

test('an order outside the actor\'s buyer access is not on the board', function () {
    importTnaDocuments();
    tnaTemplateCovering();

    $outsider = userWithPermissions(TNA_VIEW_PERMISSION);

    $this->actingAs($outsider)
        ->get(route('merchandising.tna.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 0));
});
