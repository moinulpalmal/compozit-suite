import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

/**
 * Which sidebar groups are collapsed, remembered between visits.
 *
 * **Call this once**, in `app-sidebar.tsx` — it is the only place that knows
 * every group and its links. `NavMain` is presentational and takes `expanded`
 * and `onToggle`; two instances of this hook would each hold half the collapsed
 * set and overwrite each other's cookie.
 *
 * The set is seeded from the `collapsedNavGroups` shared prop, which
 * `HandleInertiaRequests` reads from the cookie written below. Server-side so
 * the sidebar is right on first paint — `localStorage` would render every group
 * open and then snap the collapsed ones shut after hydration.
 *
 * **Navigating into a collapsed group opens it, and that counts as opening it:**
 * the label leaves the set, so the cookie is rewritten too. One invariant,
 * rather than a precedence puzzle between what the cookie says and where the
 * user actually is. Deriving it instead — "open if collapsed *or* active" —
 * was rejected: it makes the toggle a dead control on the group you are in.
 *
 * That check is React's *adjust state when a prop changes* pattern, compared
 * against a stamp of the URL it last ran for, rather than a seed at mount or an
 * effect:
 *
 * - a seed at mount never fires again, because `AppLayout` persists across
 *   Inertia visits and this hook does not remount;
 * - `setState` in an effect body is rejected by the React Compiler lint rules,
 *   the same wall `useHttp`-in-an-effect hit (ARCHITECTURE.md §8.4).
 */

const COOKIE_NAME = 'sidebar_groups';

/** A week, matching the `sidebar_state` cookie in `sidebar.tsx`. */
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

export type UseNavGroupsReturn = {
    isExpanded: (label: string) => boolean;
    toggle: (label: string) => void;
};

export function useNavGroups(groups: NavGroup[]): UseNavGroupsReturn {
    const collapsedProp = usePage().props.collapsedNavGroups;
    const { currentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(
        () => new Set(collapsedProp),
    );

    /** The group holding the page being viewed, if any. */
    const activeLabel =
        groups.find((group) =>
            group.items.some((item) => isCurrentOrParentUrl(item.href)),
        )?.label ?? null;

    const [seenUrl, setSeenUrl] = useState(currentUrl);

    if (seenUrl !== currentUrl) {
        setSeenUrl(currentUrl);

        if (activeLabel !== null && collapsed.has(activeLabel)) {
            const next = new Set(collapsed);
            next.delete(activeLabel);

            setCollapsed(next);
        }
    }

    // The cookie is a projection of the state rather than a second thing to
    // keep in step: every path that changes the set writes it, and only when
    // the set actually changed.
    useEffect(() => {
        document.cookie = `${COOKIE_NAME}=${[...collapsed].join(',')}; path=/; max-age=${COOKIE_MAX_AGE}`;
    }, [collapsed]);

    return {
        isExpanded: (label) => !collapsed.has(label),
        toggle: (label) =>
            setCollapsed((previous) => {
                const next = new Set(previous);

                if (!next.delete(label)) {
                    next.add(label);
                }

                return next;
            }),
    };
}
