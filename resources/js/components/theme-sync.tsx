import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { syncFromServer } from '@/hooks/use-appearance';

/**
 * Keeps the painted theme in step with the one the server says is stored.
 *
 * Renders nothing. It exists because `useAppearance` is deliberately free of
 * `usePage()` — it runs inside `<Toaster />`, which `withApp` mounts as a sibling
 * of the Inertia app and therefore outside its page context. This is the half
 * that *does* live in the page tree, so the `theme` shared prop finally has a
 * reader.
 *
 * What it buys: `users.theme` is authoritative, so a mismatch means a save was
 * lost — a 419 on an idle tab, a dropped connection, a session ended elsewhere.
 * Without this the wrong theme survives until the next full page load, which is
 * exactly the "it changed by itself" report. See ARCHITECTURE.md §9.5.
 */
export default function ThemeSync() {
    const theme = usePage().props.theme;

    useEffect(() => {
        syncFromServer(theme);
    }, [theme]);

    return null;
}
