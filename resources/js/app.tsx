import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Compozit Suite';

/**
 * The pages that render inside the account-settings shell.
 *
 * This is an exact list, not a `settings/` prefix, and the distinction is the
 * point. `SettingsLayout` is not a Settings layout — it is the *account*
 * layout: a fixed Profile/Security/Appearance nav and a `max-w-xl` column sized
 * for a short form. The rest of `settings/` is master data and app
 * configuration, which are full-width list screens and render under plain
 * `AppLayout` like their Admin siblings.
 *
 * A fourth account page joins by being added here, which is deliberate: it
 * should be a decision rather than something a filename does by accident.
 * See ARCHITECTURE.md §8.1.
 */
const ACCOUNT_SETTINGS_PAGES = [
    'settings/profile',
    'settings/security',
    'settings/appearance',
];

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case ACCOUNT_SETTINGS_PAGES.includes(name):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <>
                {app}
                <Toaster />
            </>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
