import { router, usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import type { ResolvedTheme, Theme } from '@/lib/themes';
import { isDarkTheme, isResolvedTheme } from '@/lib/themes';
import { update as updateThemeRoute } from '@/routes/appearance';

export type { ResolvedTheme, Theme };

export type UseAppearanceReturn = {
    readonly theme: Theme;
    readonly resolvedTheme: ResolvedTheme;
    readonly isDark: boolean;
    readonly setTheme: (theme: Theme) => void;
};

const listeners = new Set<() => void>();
let currentTheme: Theme = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const resolveTheme = (theme: Theme): ResolvedTheme => {
    if (theme !== 'system') {
        return theme;
    }

    return prefersDark() ? 'dark' : 'light';
};

const applyTheme = (theme: Theme): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const resolved = resolveTheme(theme);
    const isDark = isDarkTheme(resolved);

    document.documentElement.dataset.theme = resolved;
    document.documentElement.dataset.themePreference = theme;

    // daisyUI sets `color-scheme` itself, but `.dark` keeps `dark:` utilities working.
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentTheme);

/**
 * The preference the server rendered into <html>, which already accounts for the
 * signed-in user's stored theme and falls back to the `theme` cookie for guests.
 */
const serverTheme = (): Theme => {
    if (typeof document === 'undefined') {
        return 'system';
    }

    const preference = document.documentElement.dataset.themePreference ?? '';

    return isResolvedTheme(preference) ? preference : 'system';
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentTheme = serverTheme();
    applyTheme(currentTheme);

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

/**
 * Reads the active theme and changes it for this browser.
 *
 * Deliberately free of `usePage()`: this runs in `<Toaster />`, which `withApp`
 * renders as a sibling of the Inertia app and therefore outside its page context.
 * To also save the choice to the user's account, use `useThemeSetting`.
 */
export function useAppearance(): UseAppearanceReturn {
    const theme: Theme = useSyncExternalStore(
        subscribe,
        () => currentTheme,
        () => 'system',
    );

    const resolvedTheme = resolveTheme(theme);

    const setTheme = (next: Theme): void => {
        currentTheme = next;

        applyTheme(next);
        notify();

        // The server reads this on the next visit, and it is all guests get.
        setCookie('theme', next);
    };

    return {
        theme,
        resolvedTheme,
        isDark: isDarkTheme(resolvedTheme),
        setTheme,
    } as const;
}

export type UseThemeSettingReturn = Omit<UseAppearanceReturn, 'setTheme'> & {
    readonly updateTheme: (theme: Theme) => void;
};

/**
 * As `useAppearance`, but also persists the choice to the signed-in user's account
 * so it follows them across devices. Must be called from inside the Inertia page tree.
 */
export function useThemeSetting(): UseThemeSettingReturn {
    const { setTheme, ...appearance } = useAppearance();
    const isAuthenticated = Boolean(usePage().props.auth?.user);

    const updateTheme = (next: Theme): void => {
        // Paint straight away, then persist — no waiting on a round trip.
        setTheme(next);

        if (isAuthenticated) {
            router.patch(
                updateThemeRoute().url,
                { theme: next },
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return { ...appearance, updateTheme } as const;
}
