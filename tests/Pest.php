<?php

use App\Models\Admin\Buyer;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Cached bootstrap artifacts are removed before anything boots
|--------------------------------------------------------------------------
|
| A cached config is not merely unhelpful here, it is destructive.
| `LoadConfiguration` short-circuits on `bootstrap/cache/config.php` and never
| builds config from the environment, so **every `<env>` entry in `phpunit.xml`
| becomes inert** — `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` included.
| `RefreshDatabase` then runs `migrate:fresh` against whatever the cache names,
| which in this project is the MySQL development database. It has happened: every
| table in `compozitsuite` was dropped, twice, with no warning.
|
| The same file bakes `app.env => local`, so `$app->runningUnitTests()` is false
| and `PreventRequestForgery` answers every non-GET request with 419 — which is
| what the failure looks like from the outside, and it looks like anything but a
| caching problem.
|
| The route cache is removed for the same reason: `optimize` writes both, and
| cached routes hide newly added ones from the suite.
|
| Deleting rather than refusing is deliberate: a cached config is invalid for a
| test run *by definition* — `composer.json`'s `test` script already begins with
| `config:clear` — so there is nothing to preserve and no reason to block the
| run. `TestCase::createApplication()` is the backstop if this ever misses one.
|
*/

$cachedArtifacts = [
    'config' => $_ENV['APP_CONFIG_CACHE'] ?? __DIR__.'/../bootstrap/cache/config.php',
    'route' => __DIR__.'/../bootstrap/cache/routes-v7.php',
];

foreach ($cachedArtifacts as $kind => $cachedPath) {
    if (! is_file($cachedPath)) {
        continue;
    }

    unlink($cachedPath);

    // STDERR, so the notice never lands in `--compact`'s JSON on stdout.
    fwrite(STDERR, sprintf(
        '[tests] Removed a cached %s file (%s). It would have made phpunit.xml inert.%s',
        $kind,
        basename($cachedPath),
        PHP_EOL,
    ));
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

/*
 * `Browser` joins `Feature` here rather than getting a binding of its own: a browser
 * test needs the same application, the same fresh schema and the same permission-cache
 * reset, and only the *connection* differs — which `phpunit.browser.xml` supplies, not
 * this file. Both suites are never loaded at once, so the file-backed sqlite the browser
 * config names cannot reach a `phpunit.xml` run.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions())
    // A closure, not `fakeBreachedPasswordLookup(...)`: Pest rebinds each
    // `beforeEach` to the test case, and a first-class callable made from a
    // global function has no scope to rebind.
    ->beforeEach(function () {
        fakeBreachedPasswordLookup();
    })
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A password that satisfies `config('auth.password_policy')` in full.
 *
 * Used wherever a test needs a password to be *accepted* and the password
 * itself is not what is under test. Tests that assert a specific rule rejects
 * something spell the offending value out inline instead — see
 * `tests/Feature/PasswordPolicyTest.php`.
 *
 * The seeded/factory password is `password`, which this deliberately is not: it
 * fails four of the six rules and is in every breach corpus there is.
 */
function compliantPassword(): string
{
    return 'Str0ng-Pass!word';
}

/**
 * Answer every Have I Been Pwned range lookup with "not found".
 *
 * The password policy in `config/auth.php` includes `uncompromised`, and it is
 * the *same* policy in every environment — there is deliberately no production
 * branch, so a policy the suite does not exercise cannot exist. The cost is
 * that every password-setting test would otherwise make a live HTTPS call to
 * `api.pwnedpasswords.com`, which is slow, fails offline, and — worse — fails
 * **open**: `NotPwnedVerifier` treats a network error as "not compromised", so
 * the assertion would quietly pass without having checked anything.
 *
 * An empty 200 body is a valid range response containing no matching suffix,
 * which is exactly "this password is not in a known breach". Pass `$breached`
 * to get the opposite: the body then carries that password's own SHA-1 suffix,
 * which is what a hit looks like.
 *
 * The factory is swapped rather than re-faked because `Http::fake()` *merges*
 * stubs and the first matching one wins — a test calling it again would be
 * silently overruled by the suite-wide stub installed above.
 *
 * Requests to any other host fall through to the real handler: the closure
 * returns `null` for them rather than stubbing the whole internet.
 */
function fakeBreachedPasswordLookup(?string $breached = null): void
{
    Http::swap(new Factory(app('events')));

    $body = $breached === null
        ? ''
        : substr(strtoupper(sha1($breached)), 5).':42';

    Http::fake(fn (Request $request) => str_contains($request->url(), 'api.pwnedpasswords.com')
        ? Http::response($body, 200)
        : null);
}

/**
 * Create a verified user holding exactly the given permissions.
 *
 * The permissions are created on the fly, so a test names the abilities it
 * needs without depending on the seeded catalogue.
 */
function userWithPermissions(string ...$permissions): User
{
    $user = User::factory()->create();

    $user->givePermissionTo(array_map(
        fn (string $name): Permission => Permission::findOrCreate($name, 'web'),
        $permissions,
    ));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Assert the response flashed a toast of the given severity.
 *
 * `Inertia::flash()` writes everything under one session key, so the assertion
 * reaches into it rather than looking for a `toast` key of its own. Severity is
 * what the user sees — a colour and, for `error`, an assertive announcement — so
 * it is asserted rather than left to the controller.
 */
function assertToast(TestResponse $response, string $type): TestResponse
{
    return $response->assertSessionHas('inertia.flash_data.toast.type', $type);
}

/*
|--------------------------------------------------------------------------
| Purchase-order import helpers
|--------------------------------------------------------------------------
|
| Shared by `PurchaseOrderImportTest` and `PurchaseOrderResolveTest`, which is
| why they are here rather than in either file — a global function defined in
| one test file and used from another works only by accident of load order.
|
| The names carry a `po` prefix for the reason `poFixture()` does: Pest defines
| its own `fixture()`, and a collision in the global namespace is a fatal error
| rather than a warning.
|
*/

/** Every purchase-order permission a test might name. */
const PO_IMPORT_PERMISSION = 'merchandising.purchase-orders.import';

const PO_VIEW_PERMISSION = 'merchandising.purchase-orders.view';

const PO_DELETE_PERMISSION = 'merchandising.purchase-orders.delete';

/**
 * The redacted fixture, as a real upload.
 *
 * The `.docx` is used throughout because it is the only format that needs no
 * external binary, which keeps these files fast. That the other two formats
 * produce identical data is `PoParserTest`'s job.
 */
function poUpload(string $extension = 'docx'): UploadedFile
{
    $path = __DIR__.'/Fixtures/Merchandising/PO-SAMPLE-WALMART.'.$extension;

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
function poReissuedUpload(): UploadedFile
{
    $source = __DIR__.'/Fixtures/Merchandising/PO-SAMPLE-WALMART.docx';
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
function poImporter(Buyer $buyer, string ...$permissions): User
{
    $user = userWithPermissions(...($permissions ?: [PO_IMPORT_PERMISSION, PO_VIEW_PERMISSION]));
    $user->buyers()->attach($buyer);

    return $user;
}

/*
|--------------------------------------------------------------------------
| BQS import helpers
|--------------------------------------------------------------------------
|
| The `bqs` prefix is for the same reason the `po` one is — these live in the
| global namespace and a collision there is a fatal error.
|
| The fixture is George's real workbook, unaltered: two header rows, 89 columns
| (A–CK), six data rows in rows 3–8, eighteen `In DC Units` month columns and ten
| pack-size columns. Everything about the dynamic bands is proved against
| workbooks built in the tests themselves, because proving "any month range loads"
| needs a *second* range and shipping a second binary fixture would hide what
| differs between them.
|
*/

/** Every BQS permission a test might name. */
const BQS_IMPORT_PERMISSION = 'merchandising.bqs.import';

const BQS_VIEW_PERMISSION = 'merchandising.bqs.view';

const BQS_DELETE_PERMISSION = 'merchandising.bqs.delete';

/** George's real BQS workbook, as a real upload. */
function bqsUpload(?string $name = null): UploadedFile
{
    $path = __DIR__.'/Fixtures/Merchandising/bqs-gr4064-skater-dress.xlsx';

    return new UploadedFile($path, $name ?? 'BQS GR4064 SKATER DRESS.xlsx', null, null, true);
}

/**
 * The same workbook with one quantity altered, so it reads as the same BQS with
 * different content — which is what a genuine reissue is.
 *
 * **Renaming the file is not enough.** `source_hash` is over the bytes, so a re-upload
 * of the identical workbook is silently skipped as a duplicate and never reaches the
 * conflict step. Only the *identity* columns are left untouched, so the row keys still
 * intersect and the upload collides rather than becoming a second, unrelated BQS.
 */
function bqsRevisedUpload(): UploadedFile
{
    $source = __DIR__.'/Fixtures/Merchandising/bqs-gr4064-skater-dress.xlsx';
    $target = tempnam(sys_get_temp_dir(), 'bqs').'.xlsx';

    copy($source, $target);

    $spreadsheet = IOFactory::createReaderForFile($target)->load($target);
    // `Total BUY Units / Store` on the first data row.
    $spreadsheet->getSheet(0)->setCellValue('AM3', 12345);
    (new XlsxWriter($spreadsheet))->save($target);

    return new UploadedFile($target, 'BQS GR4064 SKATER DRESS REV2.xlsx', null, null, true);
}

/** A user who may import a BQS, and who holds the buyer being imported for. */
function bqsImporter(Buyer $buyer, string ...$permissions): User
{
    $user = userWithPermissions(...($permissions ?: [BQS_IMPORT_PERMISSION, BQS_VIEW_PERMISSION]));
    $user->buyers()->attach($buyer);

    return $user;
}

/**
 * Create a user holding the super-admin role, which bypasses every check.
 */
function superAdmin(): User
{
    $user = User::factory()->create();

    $user->assignRole(Role::findOrCreate(Role::SUPER_ADMIN, 'web'));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}
