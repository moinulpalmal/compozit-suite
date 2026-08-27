import { useEffect, useState } from 'react';
import type { ComboboxOption } from '@/components/ui/combobox';

const DEBOUNCE_MS = 400;

type OptionSearchState = {
    options: ComboboxOption[];
    loading: boolean;
};

const IDLE: OptionSearchState = { options: [], loading: false };

/**
 * Fetch combobox options from the server as the user types.
 *
 * Only active when a `searchUrl` is given — `Combobox` filters locally otherwise,
 * which is right until a list outgrows being shipped to the browser whole.
 *
 * A plain `fetch` rather than `useHttp`: this is a read with no form state behind
 * it, so `useHttp`'s new-object-per-render would mean either a ref written during
 * render or a dependency loop, both of which the React Compiler lint rules reject
 * (ARCHITECTURE.md §8.4). `use-availability.ts` is the original of this shape.
 *
 * @param searchUrl Endpoint taking `?q=` and returning `{ data: ComboboxOption[] }`.
 */
export function useOptionSearch(
    searchUrl: string | undefined,
    query: string,
): OptionSearchState {
    const trimmed = query.trim();
    const [state, setState] = useState<OptionSearchState>(IDLE);

    useEffect(() => {
        if (searchUrl === undefined) {
            return;
        }

        const controller = new AbortController();

        setState((current) => ({ ...current, loading: true }));

        const timer = setTimeout(() => {
            const separator = searchUrl.includes('?') ? '&' : '?';

            fetch(`${searchUrl}${separator}q=${encodeURIComponent(trimmed)}`, {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    // Without this, Laravel's StartSession records this URL as
                    // the session's "previous URL" — and every later `back()`
                    // (a validation failure, a guard refusal) would redirect
                    // the user onto this JSON endpoint. Same trap as
                    // `use-availability.ts`.
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) =>
                    response.ok ? response.json() : Promise.reject(response),
                )
                .then((body: { data?: ComboboxOption[] }) =>
                    setState({ options: body.data ?? [], loading: false }),
                )
                // A failed lookup shows "No matches" rather than breaking the
                // form; the server still validates whatever is submitted.
                .catch((error: unknown) => {
                    if (error instanceof DOMException && error.name === 'AbortError') {
                        return;
                    }

                    setState({ options: [], loading: false });
                });
        }, DEBOUNCE_MS);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [searchUrl, trimmed]);

    return searchUrl === undefined ? IDLE : state;
}
