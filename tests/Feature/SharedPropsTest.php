<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Shared Inertia props
|--------------------------------------------------------------------------
|
| `HandleInertiaRequests` shares a handful of props with every page
| (ARCHITECTURE.md §9.5). This file lives at the root of `tests/Feature/`
| rather than a module folder because those props belong to no module — the
| same exception `App\Http\Requests\ListRequest` takes.
|
| `collapsedNavGroups` is read from a cookie the *browser* writes, so it is
| listed in `encryptCookies(except:)`. `withUnencryptedCookie` is what pins
| that: encrypt it and Laravel discards the value as tampered, the prop comes
| back empty, and the sidebar silently forgets what the user collapsed — a
| failure with no error anywhere.
|
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('no cookie means no collapsed nav groups', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('collapsedNavGroups', []));
});

test('the collapsed nav groups cookie is shared with every page', function () {
    $this->withUnencryptedCookie('sidebar_groups', 'Admin,Reports')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('collapsedNavGroups', ['Admin', 'Reports']));
});

test('empty segments in the collapsed nav groups cookie are dropped', function () {
    // A set emptied down to one label, then re-joined, can leave these behind.
    $this->withUnencryptedCookie('sidebar_groups', ',Admin, ,')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('collapsedNavGroups', ['Admin']));
});

test('an empty collapsed nav groups cookie means nothing is collapsed', function () {
    $this->withUnencryptedCookie('sidebar_groups', '')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('collapsedNavGroups', []));
});

test('the sidebar open state is still shared alongside it', function () {
    $this->withUnencryptedCookie('sidebar_state', 'false')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sidebarOpen', false));
});
