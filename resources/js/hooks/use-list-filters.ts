import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ListFilters } from '@/types';
import type { QueryParams, RouteQueryOptions } from '@/wayfinder';

/**
 * How long a filter cell waits after the last keystroke before it visits.
 *
 * The same 400ms `use-option-search.ts` uses, and it matters more here: text
 * cells are `%term%` matches, so every visit this saves is a table scan that
 * never runs.
 */
const DEBOUNCE_MS = 400;

type Query = QueryParams;

/** Whatever Wayfinder hands `router.get` — an object, not a bare string. */
type Destination = Parameters<typeof router.get>[0];

/** A Wayfinder `index` helper, which is what every list page passes in. */
type UrlBuilder = (options: RouteQueryOptions) => Destination;

/**
 * The query state and handlers every Admin list screen wires up.
 *
 * Owns three things the four list pages would otherwise each reimplement: the
 * debounce in front of the text cells, resetting to page 1 whenever a filter
 * changes, and clearing every cell in **one** visit rather than one per cell.
 *
 * The cells' text is held here as a draft and only pushed on the debounce, so a
 * round trip never lands mid-word and clobbers what someone is still typing.
 * Nothing syncs the draft back from the server inside an effect — the draft is
 * what was sent, and `clear()` is the one thing that resets it.
 *
 * @param filters  The surface's `filters` prop, straight from `ListRequest::filters()`.
 * @param url      The surface's Wayfinder `index`, called as `url({ query })`.
 * @param only     Props to re-fetch on a filter visit — a partial reload, so a
 *                 keystroke does not reserialise the whole page. It must name
 *                 **every** prop that varies with the filtered rows, or that
 *                 prop silently goes stale.
 */
export function useListFilters<F extends ListFilters>({
    filters,
    url,
    only,
}: {
    filters: F;
    url: UrlBuilder;
    only: string[];
}) {
    const [draft, setDraft] = useState<Record<string, string>>(filters.filter);

    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Only a teardown: a debounced visit still pending when the page unmounts
    // would otherwise fire against a component that is gone.
    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const go = useCallback(
        (query: Query, partial: boolean) =>
            router.get(
                url({ query }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    ...(partial ? { only } : {}),
                },
            ),
        [only, url],
    );

    /**
     * Visit with part of the top-level query changed, resetting to page 1.
     *
     * Any change of what is listed drops the page: staying on page 9 of a
     * result set that now has two pages would show an empty table.
     */
    const visit = useCallback(
        (next: Partial<F>) =>
            go({ ...filters, ...next, page: undefined }, false),
        [filters, go],
    );

    /** Change one filter cell. Text cells debounce; dropdowns go immediately. */
    const setFilter = useCallback(
        (column: string, value: string, debounce: boolean) => {
            // The draft is the authority for the row — it already carries every
            // cell, and merging the server's copy back over it here would undo
            // whatever else is still mid-debounce.
            const merged = { ...draft, [column]: value };

            setDraft(merged);

            if (timer.current !== null) {
                clearTimeout(timer.current);
            }

            const send = () =>
                go({ ...filters, filter: merged, page: undefined }, true);

            if (!debounce) {
                send();

                return;
            }

            timer.current = setTimeout(send, DEBOUNCE_MS);
        },
        [draft, filters, go],
    );

    /** Empty every cell in one visit. */
    const clear = useCallback(() => {
        const empty = Object.fromEntries(
            Object.keys(filters.filter).map((column) => [column, '']),
        );

        setDraft(empty);

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        go({ ...filters, filter: empty, page: undefined }, false);
    }, [filters, go]);

    const hasActiveFilter = Object.values(filters.filter).some(
        (value) => value !== '',
    );

    return { draft, visit, setFilter, clear, hasActiveFilter };
}
