<?php

use App\Enums\Theme;

/*
|--------------------------------------------------------------------------
| Picking a theme, in a real browser
|--------------------------------------------------------------------------
|
| Two questions no feature test can answer, both of them the reported bug:
|
| 1. **Does the choice survive a reload?** A feature test posts to the
|    controller and sees `users.theme` change. It never runs `updateTheme()`,
|    so it cannot see that the *client* painted optimistically, fired a PATCH
|    it never checked, and left the screen disagreeing with the database. That
|    disagreement is invisible until the page is loaded again — which is
|    precisely why users described the theme as changing by itself.
|
| 2. **Does the client adopt a stored theme it disagrees with?** `<ThemeSync />`
|    reads the `theme` shared prop and re-paints on a mismatch. Before it
|    existed the prop had no reader at all, so a lost save stayed on screen
|    until the next full page load. Asserting it needs a real Inertia
|    navigation carrying real shared props.
|
| Both assert on `<html data-theme>`, because that attribute *is* the feature:
| daisyUI resolves every colour token from it.
|
| `html[lang]` rather than `html` — the plugin's `GuessLocator` only treats a
| string as CSS when `Selector::isExplicit` allows it, and a bare tag name falls
| through to `getByText()`, which matches nothing. The same trap `header[class]`
| documents in `UserMenuDismissalTest`.
|
*/

test('a chosen theme is painted at once and survives a reload', function () {
    $this->actingAs(userWithPermissions());

    visit('/settings/appearance')
        ->click('Dracula')
        // Painted before the round trip finishes — this is the optimistic half.
        ->assertDataAttribute('html[lang]', 'theme', Theme::Dracula->value)
        ->refresh()
        // And still there afterwards, which is the half that was broken: the
        // server must have stored it, or `Theme::forRequest()` would serve the
        // old value back and the theme would appear to revert on its own.
        ->assertDataAttribute('html[lang]', 'theme', Theme::Dracula->value)
        ->assertNoJavaScriptErrors();
});

/**
 * The stored theme is changed behind the browser's back, then a normal in-app
 * navigation is made. The response carries the new theme in its shared props,
 * and `<ThemeSync />` must adopt it.
 *
 * This stands in for the failure path that cannot be driven here — the plugin
 * exposes no request interception and no offline toggle, so a genuinely rejected
 * PATCH cannot be provoked. The divergence it would leave behind is identical to
 * the one created here, so this pins the mechanism that heals it.
 */
test('the client adopts the stored theme when it disagrees with what is painted', function () {
    $user = userWithPermissions();

    $this->actingAs($user);

    $page = visit('/settings/appearance')
        ->click('Nord')
        ->assertDataAttribute('html[lang]', 'theme', Theme::Nord->value);

    $user->forceFill(['theme' => Theme::Forest])->save();

    $page->click('Profile')
        ->assertPathIs('/settings/profile')
        ->assertDataAttribute('html[lang]', 'theme', Theme::Forest->value)
        ->assertNoJavaScriptErrors();
});
