<?php

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A request carrying the given signed-in theme and the given `theme` cookie.
 *
 * Either may be absent, which is the whole point: the precedence between them is
 * what {@see Theme::forRequest()} decides, and it had no test before.
 */
function themeRequest(?User $user, ?string $cookie): Request
{
    $request = Request::create('/', 'GET', [], $cookie === null ? [] : ['theme' => $cookie]);

    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

test('appearance page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk();
});

test('guests cannot view the appearance page', function () {
    $this->get(route('appearance.edit'))->assertRedirect(route('login'));
});

test('theme can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['theme' => Theme::Synthwave->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('appearance.edit'));

    expect($user->refresh()->theme)->toBe(Theme::Synthwave);
});

test('an unknown theme is rejected', function () {
    $user = User::factory()->create(['theme' => Theme::Nord]);

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['theme' => 'not-a-daisyui-theme'])
        ->assertSessionHasErrors('theme');

    expect($user->refresh()->theme)->toBe(Theme::Nord);
});

test('the stored theme is rendered into the root template', function () {
    $user = User::factory()->create(['theme' => Theme::Business]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme="business"', escape: false)
        ->assertSee('data-theme-preference="business"', escape: false)
        ->assertSee('class="dark"', escape: false);
});

test('a light theme does not add the dark class', function () {
    $user = User::factory()->create(['theme' => Theme::Cupcake]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme="cupcake"', escape: false)
        ->assertDontSee('class="dark"', escape: false);
});

test('guests fall back to the theme cookie', function () {
    $this->withUnencryptedCookie('theme', Theme::Dracula->value)
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="dracula"', escape: false);
});

test('the system preference is resolved in the browser', function () {
    $user = User::factory()->create(['theme' => Theme::System]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme-preference="system"', escape: false)
        ->assertSee('data-theme="light"', escape: false);
});

/*
|--------------------------------------------------------------------------
| The per-host cookie replant
|--------------------------------------------------------------------------
|
| `users.theme` is authoritative; the `theme` cookie is a mirror the server
| maintains so that surfaces with no authenticated user — login, 419, 500 —
| render the right theme. A cookie belongs to one host and this application
| answers on several (and will answer on more IPs after deployment), so the
| mirror is re-planted on every authenticated request rather than written once
| on save. See `HandleAppearance` and ARCHITECTURE.md §9.5.
|
*/

test('an authenticated request plants the stored theme on a host that has no cookie', function () {
    $user = User::factory()->create(['theme' => Theme::Dracula]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        // `encrypted: false` is the assertion, not a convenience: the root template
        // reads this cookie back as plain text, so an encrypted one would be useless.
        ->assertCookie('theme', Theme::Dracula->value, encrypted: false)
        ->assertCookieNotExpired('theme');
});

test('the planted cookie is unencrypted so the root template can read it back', function () {
    $user = User::factory()->create(['theme' => Theme::Nord]);

    $cookie = $this->actingAs($user)
        ->get(route('dashboard'))
        ->getCookie('theme', decrypt: false);

    expect($cookie?->getValue())->toBe(Theme::Nord->value);
});

test('a stale cookie on this host is corrected to the stored theme', function () {
    $user = User::factory()->create(['theme' => Theme::Business]);

    $this->actingAs($user)
        ->withUnencryptedCookie('theme', Theme::Cupcake->value)
        ->get(route('dashboard'))
        ->assertOk()
        // The database wins, and the cookie is brought into line with it.
        ->assertSee('data-theme="business"', escape: false)
        ->assertCookie('theme', Theme::Business->value, encrypted: false);
});

test('a cookie that already agrees is not rewritten', function () {
    $user = User::factory()->create(['theme' => Theme::Forest]);

    $this->actingAs($user)
        ->withUnencryptedCookie('theme', Theme::Forest->value)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertCookieMissing('theme');
});

test('a user who has never chosen a theme has nothing to mirror', function () {
    $user = User::factory()->create(['theme' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertCookieMissing('theme');
});

test('a guest is never given a theme cookie', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertCookieMissing('theme');
});

test('the login screen on this host inherits the theme once a user has signed in there', function () {
    $user = User::factory()->create(['theme' => Theme::Sunset]);

    $planted = $this->actingAs($user)
        ->get(route('dashboard'))
        ->getCookie('theme', decrypt: false);

    expect($planted?->getValue())->toBe(Theme::Sunset->value);

    Auth::logout();

    // A later guest request on the same host carries the cookie back.
    $this->withUnencryptedCookie('theme', $planted->getValue())
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="sunset"', escape: false);
});

/*
|--------------------------------------------------------------------------
| Theme::forRequest() precedence
|--------------------------------------------------------------------------
|
| The stored theme beats the cookie, unconditionally. That ordering is what
| makes a lost save visible as the theme reverting, so it is pinned rather than
| left to be inferred from the middleware's behaviour.
|
*/

test('the stored theme beats the cookie', function () {
    $user = User::factory()->create(['theme' => Theme::Nord]);

    expect(Theme::forRequest(themeRequest($user, Theme::Dracula->value)))
        ->toBe(Theme::Nord);
});

test('a user who has chosen nothing falls through to the cookie', function () {
    $user = User::factory()->create(['theme' => null]);

    expect(Theme::forRequest(themeRequest($user, Theme::Dracula->value)))
        ->toBe(Theme::Dracula);
});

test('a guest falls through to the cookie', function () {
    expect(Theme::forRequest(themeRequest(null, Theme::Coffee->value)))
        ->toBe(Theme::Coffee);
});

test('an unrecognised cookie falls back to system rather than throwing', function () {
    expect(Theme::forRequest(themeRequest(null, 'not-a-daisyui-theme')))
        ->toBe(Theme::System)
        ->and(Theme::forRequest(themeRequest(null, null)))
        ->toBe(Theme::System);
});
