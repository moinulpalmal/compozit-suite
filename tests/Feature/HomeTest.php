<?php

use App\Models\User;

test('guests visiting the root are sent to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users visiting the root are sent to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
