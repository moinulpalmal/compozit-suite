<?php

use App\Enums\Theme;
use App\Models\User;

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
        ->get('/')
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
