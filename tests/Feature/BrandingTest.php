<?php

use App\Models\User;

test('the app name is Compozit Suite', function () {
    expect(config('app.name'))->toBe('Compozit Suite');
});

test('the app name is shared to every page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('name', 'Compozit Suite'));
});

test('the browser tab icons are linked and present on disk', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $icons = [
        'favicon.ico' => '<link rel="icon" href="/favicon.ico" sizes="any">',
        'favicon.svg' => '<link rel="icon" href="/favicon.svg" type="image/svg+xml">',
        'apple-touch-icon.png' => '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
    ];

    foreach ($icons as $file => $tag) {
        $response->assertSee($tag, escape: false);
        expect(public_path($file))->toBeFile()
            ->and(filesize(public_path($file)))->toBeGreaterThan(0);
    }
});

test('the tab icons carry the brand mark rather than the starter kit logo', function () {
    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('#138B24')
        ->toContain('Compozit Suite');

    // PNG-in-ICO container: "\0\0\1\0" then a little-endian image count.
    $ico = file_get_contents(public_path('favicon.ico'));

    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00")
        ->and(unpack('v', substr($ico, 4, 2))[1])->toBe(3);
});
