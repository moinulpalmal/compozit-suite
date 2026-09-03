import { router } from '@inertiajs/react';
import { createElement, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
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

/**
 * The theme a save is currently in flight for, if any.
 *
 * An Inertia response that left the server *before* that save committed carries
 * the old theme in its shared props, and {@link syncFromServer} would take it as
 * authoritative and undo the paint. Holding reconciliation until the save settles
 * is what stops a navigation made straight after a click from reverting it.
 */
let pendingTheme: Theme | null = null;

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

/** Narrow an arbitrary stored value to a theme, falling back to `system`. */
const asTheme = (value: string): Theme =>
    isResolvedTheme(value) ? value : 'system';

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

    return asTheme(document.documentElement.dataset.themePreference ?? '');
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
 * Adopt the theme the server reports, when it disagrees with what is painted.
 *
 * `users.theme` is authoritative for a signed-in user, so a disagreement means a
 * save was lost — a 419 on an idle tab, a dropped connection, a session ended
 * elsewhere. Without this the wrong theme stays up until the next *full* page
 * load, which is the reverting-by-itself bug; reconciling on each Inertia
 * navigation heals it within one visit instead. Rendered by `<ThemeSync />`,
 * which is the page-tree half of this module — see ARCHITECTURE.md §9.5.
 */
export function syncFromServer(value: string): void {
    if (pendingTheme !== null) {
        return;
    }

    const next = asTheme(value);

    if (next === currentTheme) {
        return;
    }

    currentTheme = next;

    applyTheme(next);
    notify();
}

/**
 * Reads the active theme and paints it.
 *
 * Deliberately free of `usePage()`: this runs in `<Toaster />`, which `withApp`
 * renders as a sibling of the Inertia app and therefore outside its page context.
 * To also save the choice to the user's account, use `useThemeSetting`.
 *
 * Painting is all this does. The `theme` cookie is written by the server alone
 * — see `HandleAppearance` — so that a browser-written copy cannot end up with
 * different path/domain/secure attributes from the server's and become a second,
 * competing cookie of the same name once the application is behind HTTPS or a
 * new host.
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
 * As `useAppearance`, but also persists the choice to the signed-in user's
 * account so it follows them across devices and hosts.
 *
 * Every caller is behind `auth`: the two controls that use this render only on
 * `settings/appearance`. A guest cannot reach them, so there is no unauthenticated
 * branch to take.
 */
export function useThemeSetting(): UseThemeSettingReturn {
    const { setTheme, ...appearance } = useAppearance();

    const updateTheme = (next: Theme): void => {
        const previous = appearance.theme;

        // Paint straight away, then persist — no waiting on a round trip.
        setTheme(next);
        pendingTheme = next;

        const revert = (): void => {
            setTheme(previous);

            toast.error(
                createElement(
                    'span',
                    { role: 'alert' },
                    "Couldn't save your theme. Reverted to the last saved one.",
                ),
            );
        };

        router.patch(
            updateThemeRoute().url,
            { theme: next },
            {
                preserveScroll: true,
                preserveState: true,
                // A second swatch click supersedes this visit and Inertia cancels
                // it. That is the control working, not a failure, so `onCancel` is
                // deliberately absent — only a real rejection rolls the paint back.
                onError: revert,
                onException: revert,
                onFinish: () => {
                    pendingTheme = null;
                },
            },
        );
    };

    return { ...appearance, updateTheme } as const;
}
