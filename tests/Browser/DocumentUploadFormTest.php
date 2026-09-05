<?php

use App\Models\Admin\Buyer;

/*
|--------------------------------------------------------------------------
| The document upload form, driven in a real browser
|--------------------------------------------------------------------------
|
| One question, and it is the kind ARCHITECTURE.md §13.2 reserves this suite
| for: **does the form put every selected file on the wire, under the name the
| `files.*` rule validates?** `DocumentLibraryTest` cannot answer it — it hands
| the controller an array it built itself, so it passes identically against a
| form emitting `files[][]`, `file[]`, or one file out of five. That is exactly
| how `<Combobox multiple name="buyers[]">` once shipped emitting `buyers[][]`.
|
| **The assertion stops at the request body, and it has to.** The plugin's HTTP
| driver only parses `application/x-www-form-urlencoded` bodies and passes an
| empty array where uploads would go — `vendor/pestphp/pest-plugin-browser/src/
| Drivers/LaravelHttpServer.php`, where the line reads `[], // @TODO files...`.
| A multipart POST therefore reaches the application with `$_POST` and `$_FILES`
| both empty, whatever the browser sent, so **no browser test in this project can
| complete a file upload today**. Submitting here would fail on a harness gap and
| read like an application bug.
|
| What is checked instead is the half that actually regressed before: the
| `FormData` the browser would send, built from the real DOM after a real
| selection. The server half — validation, storage, the batch cap, permissions,
| buyer scope — is proved in `tests/Feature/Merchandising/DocumentLibraryTest.php`
| given a correct payload, and between the two the whole path is covered.
|
| If the plugin ever learns to carry multipart, the natural follow-up is to
| submit and assert the rows; nothing else here needs to change.
|
*/

const BROWSER_DOCUMENTS_VIEW = 'merchandising.documents.view';

const BROWSER_DOCUMENTS_CREATE = 'merchandising.documents.create';

/**
 * Select files on the input the way a person would.
 *
 * `->attach()` cannot: the plugin's helper takes a single `string $path` and calls
 * `setInputFiles`, which replaces rather than appends, so three calls leave one file.
 * `DataTransfer` is how a browser builds a multi-file `FileList`, and the one it
 * produces is the real thing — `new FormData(form)` serialises it exactly as a user's
 * own selection would be serialised.
 *
 * `script()` returns what the snippet evaluated to rather than the page, so this
 * cannot be chained.
 *
 * @param  list<string>  $names
 */
function selectFiles(object $page, array $names): void
{
    $list = json_encode($names, JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        (() => {
            const input = document.querySelector('input[name="files[]"]');
            const transfer = new DataTransfer();

            for (const name of {$list}) {
                transfer.items.add(new File(['x'], name, { type: 'application/pdf' }));
            }

            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })();
    JS);
}

/** Serialise the dialog's form the way the browser will when it submits. */
function formEntries(object $page): array
{
    $json = (string) $page->script(<<<'JS'
        (() => {
            const data = new FormData(document.querySelector('form'));
            const entries = [];

            for (const [key, value] of data.entries()) {
                entries.push(key + '=' + (value instanceof File ? 'FILE:' + value.name : value));
            }

            return JSON.stringify(entries);
        })();
    JS);

    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

test('the multiple file input puts every file on the wire under files[]', function () {
    $user = userWithPermissions(BROWSER_DOCUMENTS_VIEW, BROWSER_DOCUMENTS_CREATE);
    $user->buyers()->attach(Buyer::factory()->create(['name' => 'Walmart']));

    $this->actingAs($user);

    $page = visit('/merchandising/documents')
        ->click('Upload')
        ->click('[data-test="document-type"]')
        ->click('Size chart')
        ->fill('input[name="title"]', 'Three at once')
        ->assertSeeIn('[data-test="document-type"]', 'Size chart')
        ->assertNoJavaScriptErrors();

    selectFiles($page, ['one.pdf', 'two.pdf', 'three.pdf']);

    $entries = formEntries($page);

    /*
     * Three entries, all under `files[]`. One key means the brackets were lost and
     * only the last file would arrive; `files[][]` means none of them would match
     * the `files.*` rule at all.
     */
    expect($entries)->toContain('files[]=FILE:one.pdf')
        ->and($entries)->toContain('files[]=FILE:two.pdf')
        ->and($entries)->toContain('files[]=FILE:three.pdf');
});

/**
 * The combobox submits through a hidden input (ARCHITECTURE.md §8.5), so the value
 * the server sees is not the one on screen. This pins that the label a user clicked
 * becomes the enum value the request expects — and, for the buyer, that leaving it
 * alone submits an empty string rather than a stray placeholder.
 */
test('the comboboxes submit their values, not their labels', function () {
    $user = userWithPermissions(BROWSER_DOCUMENTS_VIEW, BROWSER_DOCUMENTS_CREATE);
    $user->buyers()->attach(Buyer::factory()->create(['name' => 'Walmart']));

    $this->actingAs($user);

    $page = visit('/merchandising/documents')
        ->click('Upload')
        ->click('[data-test="document-type"]')
        ->click('Size chart')
        // Also the wait: `script()` does not retry, so reading the form before the
        // selection has committed reads a combobox that has not chosen anything yet.
        ->assertSeeIn('[data-test="document-type"]', 'Size chart')
        ->assertNoJavaScriptErrors();

    $entries = formEntries($page);

    // The enum's backed value, not "Size chart".
    expect($entries)->toContain('file_type=size-chart')
        // Untouched and optional: an empty string, which `nullable` accepts and the
        // controller turns into a batch belonging to no buyer.
        ->and($entries)->toContain('buyer_id=');
});
