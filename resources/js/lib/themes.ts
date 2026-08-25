/**
 * daisyUI's 35 built-in themes.
 *
 * Mirrors `App\Enums\Theme`. `mode` matches each theme's `color-scheme`
 * declaration in daisyUI 5.7.22 and drives the `.dark` class on <html>, so
 * `dark:` utilities keep working under any theme.
 *
 * @see https://daisyui.com/docs/themes/
 */
export const THEMES = {
    light: 'light',
    dark: 'dark',
    cupcake: 'light',
    bumblebee: 'light',
    emerald: 'light',
    corporate: 'light',
    synthwave: 'dark',
    retro: 'light',
    cyberpunk: 'light',
    valentine: 'light',
    halloween: 'dark',
    garden: 'light',
    forest: 'dark',
    aqua: 'dark',
    lofi: 'light',
    pastel: 'light',
    fantasy: 'light',
    wireframe: 'light',
    black: 'dark',
    luxury: 'dark',
    dracula: 'dark',
    cmyk: 'light',
    autumn: 'light',
    business: 'dark',
    acid: 'light',
    lemonade: 'light',
    night: 'dark',
    coffee: 'dark',
    winter: 'light',
    dim: 'dark',
    nord: 'light',
    sunset: 'dark',
    caramellatte: 'light',
    abyss: 'dark',
    silk: 'light',
} as const satisfies Record<string, 'light' | 'dark'>;

/** A concrete daisyUI theme name. */
export type ResolvedTheme = keyof typeof THEMES;

/** A stored preference: a theme name, or `system` to follow the OS. */
export type Theme = ResolvedTheme | 'system';

export const THEME_NAMES = Object.keys(THEMES) as ResolvedTheme[];

export const isResolvedTheme = (value: string): value is ResolvedTheme =>
    value in THEMES;

export const isDarkTheme = (theme: ResolvedTheme): boolean =>
    THEMES[theme] === 'dark';

/** One selectable theme, as described by `App\Enums\Theme`. */
export type ThemeOption = {
    value: ResolvedTheme;
    label: string;
    isDark: boolean;
};
