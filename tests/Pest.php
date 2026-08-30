<?php

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions())
    ->in('Feature');

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
