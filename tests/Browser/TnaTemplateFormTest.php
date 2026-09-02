<?php

use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;
use App\Models\Settings\TnaTemplateColor;

/*
|--------------------------------------------------------------------------
| The TNA template dialog, driven in a real browser
|--------------------------------------------------------------------------
|
| This file exists because of the gap ARCHITECTURE.md §13.1 recorded: a feature
| test posts an array straight to the controller, so it cannot see what the *form*
| put on the wire. That is how `<Combobox multiple name="buyers[]">` shipped
| emitting `buyers[][]`, and how the bug below shipped too.
|
| Keep tests here to things only a DOM can answer. Anything provable by posting a
| payload belongs in `tests/Feature/Settings/TnaTemplateTest.php`, which is faster.
|
*/

const BROWSER_MASTER_DATA_VIEW = 'settings.master-data.view';

const BROWSER_MASTER_DATA_CREATE = 'settings.master-data.create';

const BROWSER_MASTER_DATA_UPDATE = 'settings.master-data.update';

/**
 * A template with a two-rung ladder, in colours named so a test can read them off
 * the screen. Faker's names are unique but unpredictable, and these assertions are
 * about *which* colour a row shows.
 *
 * @return array{0: TnaTemplate, 1: NotificationColor, 2: NotificationColor}
 */
function templateWithLadder(): array
{
    $urgent = NotificationColor::factory()->create(['name' => 'Kingfisher Urgent']);
    $calm = NotificationColor::factory()->create(['name' => 'Meadow Calm']);

    $template = TnaTemplate::factory()->withOffsets()->create(['name' => 'Ladder Under Test']);

    TnaTemplateColor::factory()->for($template, 'template')->upTo(3)->create([
        'notification_color_id' => $urgent->id,
    ]);

    TnaTemplateColor::factory()->for($template, 'template')->create([
        'notification_color_id' => $calm->id,
    ]);

    return [$template, $urgent, $calm];
}

/**
 * The empty repeater renders no inputs, so `colors` left the browser *missing*
 * rather than empty, and the `present` rule refused the form with "The colors field
 * must be present." — an internal field name, in a spelling the UI never uses.
 *
 * The feature-test half of this lives in `TnaTemplateTest` and passed before the fix
 * as well as after, because it posts `colors => []` itself. Only the browser can tell
 * the two apart, which is the whole reason this file exists.
 */
test('a template with no colour bands submits an empty colour set', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_MASTER_DATA_VIEW,
        BROWSER_MASTER_DATA_CREATE,
    ));

    $page = visit('/settings/master-data/tna-templates');

    $page->click('New template')
        ->fill('input[name="name"]', 'No colours at all')
        ->fill('input[name="lead_time_from"]', '400')
        ->fill('input[name="lead_time_to"]', '500')
        // Deliberately add no colour band — the state the dialog itself offers.
        ->assertSee('No bands yet')
        ->click('Create template')
        ->assertSee('No colours at all')
        ->assertDontSee('colors field')
        ->assertNoJavaScriptErrors();

    $template = TnaTemplate::with('colors')->sole();

    expect($template->name)->toBe('No colours at all')
        ->and($template->colors)->toHaveCount(0);
});

/**
 * Every band rendered its placeholder instead of its colour.
 *
 * `assignableOptions()` emits `value` as an `int`; the ladder seeded each rung with
 * `String($id)`, and `Combobox` resolved the selection with `===`. `'3' === 3` is
 * false, so `selected` was null and the trigger fell back to "Choose a colour" —
 * while the hidden input went on submitting the right id. A feature test posting a
 * payload cannot see either half of that.
 */
test('the edit dialog shows the colour each band already uses', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_MASTER_DATA_VIEW,
        BROWSER_MASTER_DATA_UPDATE,
    ));

    [$template] = templateWithLadder();

    visit('/settings/master-data/tna-templates')
        ->click('[aria-label="Edit '.$template->name.'"]')
        // "Colour bands" is the list's column header too, so it cannot tell an open
        // dialog from a closed one. "Add band" only ever renders inside the ladder.
        ->assertSee('Add band')
        // Scoped to the triggers: the closed menu holds the same names, so an
        // unscoped assertion would pass with the bug still in place.
        ->assertSeeIn('[data-test="band-color-0"]', 'Kingfisher Urgent')
        ->assertSeeIn('[data-test="band-color-1"]', 'Meadow Calm')
        ->assertDontSee('Choose a colour')
        ->assertNoJavaScriptErrors();
});

/**
 * The bug was display-only — the hidden input still carried the id — and this is
 * what pins that, so a future change to the matching cannot quietly start blanking
 * a ladder nobody touched.
 */
test('saving an untouched ladder preserves every band', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_MASTER_DATA_VIEW,
        BROWSER_MASTER_DATA_UPDATE,
    ));

    [$template, $urgent, $calm] = templateWithLadder();

    visit('/settings/master-data/tna-templates')
        ->click('[aria-label="Edit '.$template->name.'"]')
        ->click('Save changes')
        // The toast is the only thing that says the round trip finished; the
        // assertions below do not retry, so without this they race it.
        ->waitForText('TNA template updated')
        // The panel unmounts its children on close, so the ladder is gone once the
        // save lands. Not "Colour bands" — that is the list's column header as well.
        ->assertDontSee('Add band')
        ->assertNoJavaScriptErrors();

    $bands = $template->fresh('colors')->colors;

    expect($bands->pluck('notification_color_id')->all())->toBe([$urgent->id, $calm->id])
        ->and($bands->pluck('max_days_remaining')->all())->toBe([3, null]);
});

/**
 * `DialogContent` used to render its children whether the panel was open or not, so
 * nothing ever unmounted: the ladder's `useState` ran once at page load and Cancel
 * discarded nothing. Reopening showed the edit you had abandoned.
 */
test('reopening the dialog shows the saved template, not the last edit', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_MASTER_DATA_VIEW,
        BROWSER_MASTER_DATA_UPDATE,
    ));

    [$template] = templateWithLadder();

    visit('/settings/master-data/tna-templates')
        ->click('[aria-label="Edit '.$template->name.'"]')
        ->click('Add band')
        // The row just added is the only one with no colour chosen.
        ->assertSee('Choose a colour')
        ->click('Cancel')
        ->click('[aria-label="Edit '.$template->name.'"]')
        ->assertSee('Add band')
        ->assertDontSee('Choose a colour')
        ->assertNoJavaScriptErrors();
});
