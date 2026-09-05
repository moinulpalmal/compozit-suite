<?php

use App\Models\Admin\Designation;

/*
|--------------------------------------------------------------------------
| The form-modal standard, driven in a real browser
|--------------------------------------------------------------------------
|
| ARCHITECTURE.md §8.10 states the contract every insert/update modal in this
| application wears — Cancel, Save & add another, Save & close, and what each
| does to the form and the panel. **Every clause of it is invisible to a feature
| test**, which posts a payload to the controller and never learns whether a
| panel closed, whether a form emptied, or where focus went. That is exactly the
| gap §13.2 keeps this suite for.
|
| It is proved once, here, against `admin/designations` rather than once per
| dialog: the ten modals share `hooks/use-form-dialog.ts` and
| `components/shared/form-dialog-footer.tsx`, so a regression in the contract is
| a regression in those two files. Designations is the smallest surface that
| still carries a `Combobox`, which is the control the clearing rule exists for —
| it submits through a hidden input and would survive Inertia's `reset()`.
|
| What is deliberately *not* here: the per-dialog field rules. Those are feature
| tests, and `tests/Feature/Admin/DesignationTest.php` has them.
|
*/

const BROWSER_FORM_DIALOG_VIEW = 'admin.designations.view';

const BROWSER_FORM_DIALOG_CREATE = 'admin.designations.create';

const BROWSER_FORM_DIALOG_UPDATE = 'admin.designations.update';

/**
 * Read a control's submitted value out of the open dialog.
 *
 * Scoped to the `<form>`, because the list behind the panel holds the same text
 * and an unscoped read would pass with the form still full.
 *
 * `script()` returns what the snippet evaluated to rather than the page, so this
 * cannot be chained — and it does not retry, so every call has to be preceded by
 * an assertion that waits for the state it is about to read.
 */
function dialogValue(object $page, string $name): string
{
    return (string) $page->script(<<<JS
        (() => document.querySelector('form [name="{$name}"]')?.value ?? '')();
    JS);
}

/** Whether the named control is currently painted as rejected. */
function dialogInvalid(object $page, string $name): bool
{
    return (bool) $page->script(<<<JS
        (() => {
            const form = document.querySelector('form');
            const control = form?.querySelector('#{$name}') ?? form?.elements['{$name}'];

            return control?.getAttribute('aria-invalid') === 'true';
        })();
    JS);
}

/** The `id` of whatever currently holds focus — how the focus rules are asserted. */
function focusedId(object $page): string
{
    return (string) $page->script('(() => document.activeElement?.id ?? "")();');
}

/**
 * A create modal, filled in and ready to submit.
 *
 * The status combobox is deliberately moved off its default: a control left
 * untouched proves nothing about clearing, and this is the one that Inertia's
 * `reset()` would have failed to restore.
 */
function openCreateDialog(object $page, string $name): object
{
    return $page->click('New designation')
        ->fill('input[name="name"]', $name)
        ->fill('input[name="short_form"]', 'XQZ')
        ->click('[data-test="designation-status"]')
        ->click('Inactive')
        ->assertSeeIn('[data-test="designation-status"]', 'Inactive');
}

/*
|--------------------------------------------------------------------------
| Save & add another
|--------------------------------------------------------------------------
*/

/**
 * The clause the whole standard exists for: the record lands, the panel stays,
 * and the form comes back empty — including the combobox, which is React state
 * behind a hidden input and is what makes a `key` remount the mechanism rather
 * than `reset()`.
 */
test('save & add another saves the row, keeps the panel open and clears the form', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    $page = visit('/admin/designations');

    openCreateDialog($page, 'Cutting Master')
        ->click('Save & add another')
        // The toast is the only thing that says the round trip finished, and the
        // reads below do not retry.
        ->waitForText('Designation created.')
        // "Save & add another" only ever renders inside the panel, so it is what
        // tells an open dialog from a closed one. The designation's name is on
        // the list behind it and cannot.
        ->assertSee('Save & add another')
        ->assertSeeIn('[data-test="designation-status"]', 'Active')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('')
        ->and(dialogValue($page, 'short_form'))->toBe('')
        ->and(dialogValue($page, 'status'))->toBe('A')
        // Focus returns to the first field, so the next record can be typed
        // without reaching for the mouse — §8.10's `autoFocus` rule.
        ->and(focusedId($page))->toBe('name');

    expect(Designation::where('name', 'Cutting Master')->exists())->toBeTrue();
});

/**
 * The other half, and the one a naive implementation gets wrong: a *failed*
 * save must not clear anything. Retyping a rejected form is the worst outcome
 * the standard can produce, so the remount is bound to success alone.
 */
test('a rejected save & add another keeps the panel, the values and the error', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    Designation::factory()->create(['name' => 'Cutting Master']);

    $page = visit('/admin/designations');

    openCreateDialog($page, 'Cutting Master')
        ->click('Save & add another')
        ->waitForText('A designation with that name already exists.')
        ->assertSee('Save & add another')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('Cutting Master')
        ->and(dialogValue($page, 'short_form'))->toBe('XQZ')
        ->and(dialogValue($page, 'status'))->toBe('I')
        // The field is painted as rejected and holds focus — a message alone
        // leaves a long modal looking like it simply did not close.
        ->and(dialogInvalid($page, 'name'))->toBeTrue()
        ->and(focusedId($page))->toBe('name');

    expect(Designation::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Save & close
|--------------------------------------------------------------------------
*/

test('save & close saves the row and closes the panel', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    $page = visit('/admin/designations');

    openCreateDialog($page, 'Finishing Manager')
        ->click('Save & close')
        ->waitForText('Designation created.')
        ->assertDontSee('Save & add another')
        ->assertNoJavaScriptErrors();

    expect(Designation::where('name', 'Finishing Manager')->exists())->toBeTrue();
});

test('a rejected save & close leaves the panel open', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    Designation::factory()->create(['name' => 'Finishing Manager']);

    $page = visit('/admin/designations');

    openCreateDialog($page, 'Finishing Manager')
        ->click('Save & close')
        ->waitForText('A designation with that name already exists.')
        ->assertSee('Save & add another')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('Finishing Manager')
        ->and(Designation::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Cancel
|--------------------------------------------------------------------------
*/

/**
 * Cancel needs no code of its own — `DialogContent` unmounts its children on
 * close, so the next open re-seeds from props. That is precisely why it needs a
 * test: nothing in the dialog says out loud that it happens, and a future change
 * to the mount guard would take this with it silently.
 */
test('cancel closes the panel and the form comes back empty', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    $page = visit('/admin/designations');

    openCreateDialog($page, 'Abandoned Title')
        ->click('Cancel')
        ->assertDontSee('Save & add another')
        ->click('New designation')
        ->assertSee('Save & add another')
        ->assertSeeIn('[data-test="designation-status"]', 'Active')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('')
        ->and(dialogValue($page, 'status'))->toBe('A')
        ->and(Designation::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The create/edit split, and Enter
|--------------------------------------------------------------------------
*/

/**
 * An edit modal posts to *that record's* update route, so "Save & add another"
 * would re-save the same row rather than create a new one. It is not offered,
 * and "clear" there means back to the stored row rather than empty.
 */
test('an edit modal offers no save & add another, and cancel restores the row', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_UPDATE,
    ));

    $designation = Designation::factory()->create(['name' => 'Quality Head']);

    $page = visit('/admin/designations');

    $page->click('[aria-label="Edit '.$designation->name.'"]')
        ->assertSee('Save & close')
        ->assertDontSee('Save & add another')
        ->fill('input[name="name"]', 'Something Else')
        ->click('Cancel')
        ->click('[aria-label="Edit '.$designation->name.'"]')
        ->assertSee('Save & close')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('Quality Head');
});

/**
 * Implicit submission fires the *first* submit button in tree order, which the
 * footer's DOM order makes "Save & add another" on a create modal. That is the
 * decision §8.10 records, and it only holds while visual order and DOM order
 * agree — so it is pinned rather than left to whoever next edits the footer.
 */
test('enter in a field fires the leftmost save button', function () {
    $this->actingAs(userWithPermissions(
        BROWSER_FORM_DIALOG_VIEW,
        BROWSER_FORM_DIALOG_CREATE,
    ));

    $page = visit('/admin/designations');

    // `keys()`, not `press()` — the latter is Dusk's "click the button with this
    // label" and would look for a button reading "Enter".
    openCreateDialog($page, 'Store Keeper')
        ->keys('input[name="name"]', 'Enter')
        ->waitForText('Designation created.')
        // Saved *and* still open: the leftmost save is "Save & add another".
        ->assertSee('Save & add another')
        ->assertNoJavaScriptErrors();

    expect(dialogValue($page, 'name'))->toBe('')
        ->and(Designation::where('name', 'Store Keeper')->exists())->toBeTrue();
});
