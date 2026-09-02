<?php

use App\Models\Settings\TnaTemplate;

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
