<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * The daisyUI themes the application ships with.
 *
 * Values match daisyUI's built-in theme names exactly and are written to the
 * `data-theme` attribute on the <html> element. `System` is not a daisyUI theme:
 * it defers to the visitor's `prefers-color-scheme` and resolves to Light or Dark
 * in the browser.
 *
 * @see https://daisyui.com/docs/themes/
 */
enum Theme: string
{
    case System = 'system';

    case Light = 'light';
    case Dark = 'dark';
    case Cupcake = 'cupcake';
    case Bumblebee = 'bumblebee';
    case Emerald = 'emerald';
    case Corporate = 'corporate';
    case Synthwave = 'synthwave';
    case Retro = 'retro';
    case Cyberpunk = 'cyberpunk';
    case Valentine = 'valentine';
    case Halloween = 'halloween';
    case Garden = 'garden';
    case Forest = 'forest';
    case Aqua = 'aqua';
    case Lofi = 'lofi';
    case Pastel = 'pastel';
    case Fantasy = 'fantasy';
    case Wireframe = 'wireframe';
    case Black = 'black';
    case Luxury = 'luxury';
    case Dracula = 'dracula';
    case Cmyk = 'cmyk';
    case Autumn = 'autumn';
    case Business = 'business';
    case Acid = 'acid';
    case Lemonade = 'lemonade';
    case Night = 'night';
    case Coffee = 'coffee';
    case Winter = 'winter';
    case Dim = 'dim';
    case Nord = 'nord';
    case Sunset = 'sunset';
    case Caramellatte = 'caramellatte';
    case Abyss = 'abyss';
    case Silk = 'silk';

    /**
     * Whether the theme renders on a dark surface.
     *
     * Mirrors the `color-scheme` declaration of each built-in theme in
     * daisyUI 5.7.22. `System` is resolved in the browser, so it reports false.
     */
    public function isDark(): bool
    {
        return in_array($this, self::darkThemes(), strict: true);
    }

    /**
     * The human readable name shown in the theme picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Cmyk => 'CMYK',
            self::Lofi => 'Lo-Fi',
            self::Caramellatte => 'Caramel Latte',
            default => ucfirst($this->value),
        };
    }

    /**
     * The theme actually written to `data-theme`, falling back for `System`.
     */
    public function resolve(bool $prefersDark = false): self
    {
        if ($this !== self::System) {
            return $this;
        }

        return $prefersDark ? self::Dark : self::Light;
    }

    /**
     * The theme in effect for a request.
     *
     * The authenticated user's stored theme wins; guests fall back to the
     * unencrypted `theme` cookie the browser writes on every change.
     */
    public static function forRequest(Request $request): self
    {
        $stored = $request->user()?->theme;

        if ($stored instanceof self) {
            return $stored;
        }

        $cookie = $request->cookie('theme');

        return (is_string($cookie) ? self::tryFrom($cookie) : null) ?? self::System;
    }

    /**
     * Every selectable daisyUI theme, excluding the `System` sentinel.
     *
     * @return list<self>
     */
    public static function themes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $theme): bool => $theme !== self::System,
        ));
    }

    /**
     * The themes daisyUI declares with `color-scheme: dark`.
     *
     * @return list<self>
     */
    public static function darkThemes(): array
    {
        return [
            self::Dark,
            self::Synthwave,
            self::Halloween,
            self::Forest,
            self::Aqua,
            self::Black,
            self::Luxury,
            self::Dracula,
            self::Business,
            self::Night,
            self::Coffee,
            self::Dim,
            self::Sunset,
            self::Abyss,
        ];
    }
}
