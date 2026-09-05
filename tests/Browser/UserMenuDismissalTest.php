<?php

/*
|--------------------------------------------------------------------------
| The sidebar user menu, driven in a real browser
|--------------------------------------------------------------------------
|
| One question, and no feature test can answer it: **does the menu leave the
| screen when it is dismissed?**
|
| It did not, for every user, in every engine. `dropdown-menu.tsx` renders the
| menu with `popover="auto"`, so the browser — not our code — supplies Escape,
| outside-click and the trigger's toggle. All three worked. What did not was the
| hiding: the element carried daisyUI's `.menu`, which sets `display:flex`, and
| an author declaration beats the User-Agent rule
| `[popover]:not(:popover-open){display:none}` unconditionally, because
| specificity and `@layer` only order rules within one origin. So `hidePopover()`
| ran, `:popover-open` went false, and the menu stayed on screen — visible even
| on a fresh page load, before anything had been clicked.
|
| That is invisible to every other kind of test. The markup was correct, the
| state was correct, `:popover-open` was correct; only the rendered result was
| wrong, and only a real engine resolving a real cascade can see it. The
| assertions below therefore key off `assertSee`/`assertDontSee`, which read
| *visible* text — the one property that was broken.
|
| The regression this guards against is subtle and cheap to reintroduce: any
| future class on that element that happens to set `display` — `flex`, `grid`,
| `block` — brings the whole bug back with no other symptom.
|
*/

/**
 * The menu is closed when its contents cannot be seen.
 *
 * `assertDontSee` is the assertion that matters here rather than a check on
 * `:popover-open`: the bug left `:popover-open` reporting false while the menu
 * sat on screen, so asserting on it would have passed against the broken code.
 */
test('the user menu is not on screen until it is opened', function () {
    $this->actingAs(userWithPermissions());

    visit('/dashboard')
        ->assertDontSee('Log out')
        ->assertNoJavaScriptErrors();
});

test('clicking the trigger a second time closes the user menu', function () {
    $this->actingAs(userWithPermissions());

    visit('/dashboard')
        ->click('[data-test="sidebar-menu-button"]')
        ->assertSee('Log out')
        ->click('[data-test="sidebar-menu-button"]')
        ->assertDontSee('Log out')
        ->assertNoJavaScriptErrors();
});

/**
 * Escape is pressed on the trigger because that is where the focus is: a
 * `popover="auto"` shown by a mouse click does not move focus into itself, so the
 * trigger still holds it. This is the real keyboard path, not a synthetic one.
 */
test('escape closes the user menu', function () {
    $this->actingAs(userWithPermissions());

    visit('/dashboard')
        ->click('[data-test="sidebar-menu-button"]')
        ->assertSee('Log out')
        ->keys('[data-test="sidebar-menu-button"]', 'Escape')
        ->assertDontSee('Log out')
        ->assertNoJavaScriptErrors();
});

/**
 * The header is the safe outside target: it is wide, always present in the sidebar
 * layout, and holds no link or button at its centre, so the click dismisses the
 * menu without navigating away from the page under test.
 *
 * `header[class]` rather than `header`. The plugin's `GuessLocator` only treats a
 * string as CSS when `Selector::isExplicit` says so, and a bare tag name is not on
 * that list — it falls through to `getByText('header')`, which matches nothing, and
 * the click silently lands on no element at all. The `[class]` makes it explicit
 * without pinning any particular utility class.
 */
test('clicking outside closes the user menu', function () {
    $this->actingAs(userWithPermissions());

    visit('/dashboard')
        ->click('[data-test="sidebar-menu-button"]')
        ->assertSee('Log out')
        ->click('header[class]')
        ->assertDontSee('Log out')
        ->assertNoJavaScriptErrors();
});

/*
 * There is deliberately no test for "choosing an item closes the menu".
 *
 * Both items navigate, so the assertion after the click would be reading a freshly
 * mounted page whose menu has never been opened — it would pass against a menu that
 * cannot close at all, which is precisely the bug. ARCHITECTURE.md §13.2 asks that a
 * browser test earn its place; that one would not.
 */
