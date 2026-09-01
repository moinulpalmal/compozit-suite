<?php

use App\Enums\Merchandising\TnaMilestone;
use App\Enums\RecordStatus;
use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;

/*
|--------------------------------------------------------------------------
| The TNA template register
|--------------------------------------------------------------------------
|
| Settings' second master-data surface. Most of its behaviour is the shape
| `NotificationColorTest` already proves, so what is tested here is the three
| rules the database cannot carry — no overlapping active bands, one catch-all
| rung, and no offset for a milestone that is read from the purchase order —
| plus the deletion guard this feature makes owed on notification colours.
|
*/

const MASTER_DATA_VIEW = 'settings.master-data.view';

const MASTER_DATA_CREATE = 'settings.master-data.create';

const MASTER_DATA_UPDATE = 'settings.master-data.update';

const MASTER_DATA_DELETE = 'settings.master-data.delete';

beforeEach(function (): void {
    $this->actingAs(userWithPermissions(
        MASTER_DATA_VIEW,
        MASTER_DATA_CREATE,
        MASTER_DATA_UPDATE,
        MASTER_DATA_DELETE,
    ));
});

/** A complete, valid payload the individual tests vary one field of. */
function tnaTemplatePayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Long lead',
        'lead_time_from' => 241,
        'lead_time_to' => 300,
        'status' => RecordStatus::Active->value,
        'milestones' => [
            ['milestone' => TnaMilestone::TrimsApproval->value, 'offset_days' => 10],
            ['milestone' => TnaMilestone::ProductionSampleApproval->value, 'offset_days' => 12],
        ],
        'colors' => [
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => 7],
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => null],
        ],
    ], $overrides);
}

test('a template is created with its offsets and colour ladder', function () {
    $response = $this->post(
        route('settings.master-data.tna-templates.store'),
        tnaTemplatePayload(),
    );

    assertToast($response, 'success');

    $template = TnaTemplate::with(['milestones', 'colors'])->sole();

    expect($template->lead_time_from)->toBe(241)
        ->and($template->lead_time_to)->toBe(300)
        ->and($template->milestones)->toHaveCount(2)
        ->and($template->offsetFor(TnaMilestone::TrimsApproval))->toBe(10)
        ->and($template->offsetFor(TnaMilestone::ProductionSampleApproval))->toBe(12)
        ->and($template->colors)->toHaveCount(2);
});

test('the colour ladder is ordered with the catch-all last, whatever order it was saved in', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'colors' => [
            // Deliberately saved worst-first-last: the relation must reorder it.
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => null],
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => 21],
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => -1],
        ],
    ]));

    $bounds = TnaTemplate::sole()->colors->pluck('max_days_remaining')->all();

    expect($bounds)->toBe([-1, 21, null]);
});

test('two active bands may not overlap', function () {
    TnaTemplate::factory()->covering(241, 300)->create(['name' => 'Existing']);

    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'name' => 'Overlapping',
        'lead_time_from' => 280,
        'lead_time_to' => 400,
    ]))->assertSessionHasErrors('lead_time_from');

    expect(TnaTemplate::count())->toBe(1);
});

test('bands that merely touch at the boundary are not an overlap', function () {
    TnaTemplate::factory()->covering(241, 300)->create(['name' => 'Existing']);

    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'name' => 'Next band',
        'lead_time_from' => 301,
        'lead_time_to' => 400,
    ]))->assertSessionHasNoErrors();

    expect(TnaTemplate::count())->toBe(2);
});

test('a retired band may overlap its replacement', function () {
    TnaTemplate::factory()->covering(241, 300)->inactive()->create(['name' => 'Retired']);

    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'name' => 'Replacement',
    ]))->assertSessionHasNoErrors();

    expect(TnaTemplate::count())->toBe(2);
});

test('a template may be edited without overlapping itself', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload());
    $template = TnaTemplate::sole();

    $this->put(
        route('settings.master-data.tna-templates.update', $template),
        tnaTemplatePayload(['lead_time_to' => 320]),
    )->assertSessionHasNoErrors();

    expect($template->fresh()->lead_time_to)->toBe(320);
});

test('editing replaces the children rather than adding to them', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload());
    $template = TnaTemplate::sole();

    $this->put(
        route('settings.master-data.tna-templates.update', $template),
        tnaTemplatePayload([
            'milestones' => [
                ['milestone' => TnaMilestone::TrimsApproval->value, 'offset_days' => 25],
            ],
            'colors' => [
                ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => null],
            ],
        ]),
    )->assertSessionHasNoErrors();

    $template = $template->fresh(['milestones', 'colors']);

    expect($template->milestones)->toHaveCount(1)
        ->and($template->offsetFor(TnaMilestone::TrimsApproval))->toBe(25)
        ->and($template->colors)->toHaveCount(1);
});

test('only one band may be left open-ended', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'colors' => [
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => null],
            ['notification_color_id' => NotificationColor::factory()->create()->id, 'max_days_remaining' => null],
        ],
    ]))->assertSessionHasErrors('colors.1.max_days_remaining');

    expect(TnaTemplate::count())->toBe(0);
});

test('one colour may not be used by two bands of the same template', function () {
    $color = NotificationColor::factory()->create();

    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'colors' => [
            ['notification_color_id' => $color->id, 'max_days_remaining' => 7],
            ['notification_color_id' => $color->id, 'max_days_remaining' => null],
        ],
    ]))->assertSessionHasErrors('colors.1.notification_color_id');
});

test('shipment cannot be scheduled by a template', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'milestones' => [
            ['milestone' => TnaMilestone::Shipment->value, 'offset_days' => 200],
        ],
    ]))->assertSessionHasErrors('milestones.0.milestone');

    expect(TnaTemplate::count())->toBe(0);
});

test('the band may not end before it starts', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload([
        'lead_time_from' => 300,
        'lead_time_to' => 241,
    ]))->assertSessionHasErrors('lead_time_to');
});

test('deleting a notification colour a template paints with is refused', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload());

    $inUse = TnaTemplate::with('colors')->sole()->colors->first()->color;

    $response = $this->delete(
        route('settings.master-data.notification-colors.destroy', $inUse),
    );

    assertToast($response, 'warning');

    expect(NotificationColor::whereKey($inUse->id)->exists())->toBeTrue();
});

test('a notification colour nothing uses is still deletable', function () {
    $spare = NotificationColor::factory()->create();

    $response = $this->delete(
        route('settings.master-data.notification-colors.destroy', $spare),
    );

    assertToast($response, 'success');

    expect(NotificationColor::whereKey($spare->id)->exists())->toBeFalse();
});

test('deleting a template takes its children with it', function () {
    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload());
    $template = TnaTemplate::sole();

    $response = $this->delete(route('settings.master-data.tna-templates.destroy', $template));

    assertToast($response, 'success');

    expect(TnaTemplate::count())->toBe(0)
        ->and(DB::table('tna_template_milestones')->count())->toBe(0)
        ->and(DB::table('tna_template_colors')->count())->toBe(0);
});

test('the register is gated on the master-data permissions', function () {
    $this->actingAs(userWithPermissions(MASTER_DATA_VIEW));

    $this->get(route('settings.master-data.tna-templates.index'))->assertOk();

    $this->post(route('settings.master-data.tna-templates.store'), tnaTemplatePayload())
        ->assertForbidden();
});
