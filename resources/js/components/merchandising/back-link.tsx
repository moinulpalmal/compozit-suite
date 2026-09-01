import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** The query-string key a list uses to hand a detail page its own address back. */
export const RETURN_PARAM = 'back';

type Props = {
    /** Where to go when the detail page was not reached from its list. */
    fallback: string;
    /** What the destination is called, e.g. "BQS" or "Purchase orders". */
    label: string;
};

/**
 * Returns to the list a detail page was opened from, **with its state intact**.
 *
 * A list carries its whole query string — `?filter[season]=SS&sort=bqs_date&page=3`
 * (ARCHITECTURE.md §8.6) — and a plain link to `index()` has none of it. Open a record
 * from page 3 of a filtered list, come back, and you land on page 1 unfiltered with
 * your work gone. So the list hands its own address forward in a `back` parameter
 * ({@see buildReturnQuery}) and this reads it out again.
 *
 * **The breadcrumb deliberately still goes to the bare index.** The owner asked for
 * the button and for the breadcrumb to be left alone, so the two do genuinely differ:
 * the button returns you to where you were, the crumb returns you to the top of the
 * list. That is a decision, not drift — do not "fix" the crumb to match without asking.
 *
 * `usePage().url` rather than `window.location`: Inertia supplies it on the server too
 * (ARCHITECTURE.md §2 — SSR runs in Vite dev mode), and reading `window` during render
 * would break there.
 *
 * **This is the application's first back control.** It lives under
 * `components/merchandising/` because both callers are Merchandising pages; §6.5 says
 * it moves to `components/shared/` the moment a second module imports it, which is
 * likely as soon as any other detail page wants one.
 */
export default function BackLink({ fallback, label }: Props) {
    const { url } = usePage();

    const href = returnHrefFrom(url, fallback);

    return (
        <Button variant="ghost" size="sm" asChild>
            <Link href={href} data-test="back-link">
                <ArrowLeft /> {label}
            </Link>
        </Button>
    );
}

/**
 * Rebuild the list address out of the current page's `back` parameter.
 *
 * Exported for testing and reuse; the fallback is returned whenever the parameter is
 * absent — a detail page reached from a pasted URL, a bookmark or a new tab has no
 * list state to go back to, and the top of the list is the honest answer there.
 */
export function returnHrefFrom(currentUrl: string, fallback: string): string {
    // `url` is path + query, so a base is needed only to parse it.
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    const back = query.get(RETURN_PARAM);

    return back ? `${fallback}?${back}` : fallback;
}

/**
 * Encode a list's current state as the value of the `back` parameter.
 *
 * Built from the `filters` prop and the paginator rather than from
 * `window.location.search`, for two reasons: it is SSR-safe, and
 * `ListRequest::filters()` is the definition of what a list's state *is* — reading the
 * address bar would also carry along anything else that happened to be in it.
 *
 * **`page` is not part of `filters()`** and has to come from the paginator, which is
 * the whole reason this takes two arguments. Without it "back" returns you to the
 * right filters on the wrong page.
 *
 * Empty filter cells are dropped so a pristine list produces a short, readable URL.
 */
export function buildReturnQuery(
    filters: {
        sort: string;
        direction: string;
        per_page: number;
        filter: Record<string, string>;
        view?: string;
    },
    currentPage: number,
): string {
    const query = new URLSearchParams();

    query.set('sort', filters.sort);
    query.set('direction', filters.direction);
    query.set('per_page', String(filters.per_page));

    if (filters.view) {
        query.set('view', filters.view);
    }

    for (const [column, value] of Object.entries(filters.filter)) {
        if (value !== '') {
            query.set(`filter[${column}]`, value);
        }
    }

    if (currentPage > 1) {
        query.set('page', String(currentPage));
    }

    return query.toString();
}
