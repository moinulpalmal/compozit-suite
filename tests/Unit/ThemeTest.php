<?php

use App\Enums\Theme;

/**
 * Parse the light/dark map out of the frontend theme registry.
 *
 * `resources/js/lib/themes.ts` has to agree with `App\Enums\Theme`: the browser
 * uses it to toggle the `.dark` class synchronously, before any prop is available.
 *
 * @return array<string, string>
 */
function frontendThemeRegistry(): array
{
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/themes.ts');

    preg_match('/export const THEMES = \{(.*?)\n\} as const/s', $source, $block);

    preg_match_all("/^\s{4}(\w+): '(light|dark)',$/m", $block[1], $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

test('every daisyUI theme is selectable', function () {
    expect(Theme::themes())->toHaveCount(35)
        ->and(Theme::cases())->toHaveCount(36)
        ->and(Theme::themes())->not->toContain(Theme::System);
});

test('the dark themes match daisyUI', function () {
    expect(Theme::darkThemes())->toHaveCount(14);

    expect(Theme::Synthwave->isDark())->toBeTrue()
        ->and(Theme::Abyss->isDark())->toBeTrue()
        ->and(Theme::Cupcake->isDark())->toBeFalse()
        ->and(Theme::Cyberpunk->isDark())->toBeFalse()
        ->and(Theme::System->isDark())->toBeFalse();
});

test('the frontend theme registry mirrors the enum', function () {
    $registry = frontendThemeRegistry();

    expect($registry)->toHaveCount(35);

    foreach (Theme::themes() as $theme) {
        expect($registry)->toHaveKey($theme->value);

        expect($registry[$theme->value])
            ->toBe($theme->isDark() ? 'dark' : 'light', "Theme [{$theme->value}] disagrees on its colour scheme.");
    }
});

test('system resolves to a concrete theme', function () {
    expect(Theme::System->resolve())->toBe(Theme::Light)
        ->and(Theme::System->resolve(prefersDark: true))->toBe(Theme::Dark)
        ->and(Theme::Nord->resolve(prefersDark: true))->toBe(Theme::Nord);
});

test('theme labels are humanised', function () {
    expect(Theme::Cmyk->label())->toBe('CMYK')
        ->and(Theme::Lofi->label())->toBe('Lo-Fi')
        ->and(Theme::Caramellatte->label())->toBe('Caramel Latte')
        ->and(Theme::Synthwave->label())->toBe('Synthwave');
});
