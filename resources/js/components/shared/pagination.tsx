import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/** Pages either side of the current one before the list is elided. */
const WINDOW = 2;

/**
 * Numbered paging with previous/next and a "showing x–y of z" read-out.
 *
 * The page numbers are computed here from `current_page` and `last_page` rather
 * than rendered from the paginator's own `links` array: those labels are HTML
 * entities (`&laquo; Previous`) and bake previous/next into the same list, which
 * is a worse fit than deriving the window.
 *
 * Lived in `components/admin/` while Admin was its only consumer, and moved
 * here when the Settings master-data list became the second — ARCHITECTURE.md
 * §6.5.
 */
export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-base-content/60">
                Showing <span className="tabular-nums">{page.from ?? 0}</span>–
                <span className="tabular-nums">{page.to ?? 0}</span> of{' '}
                <span className="tabular-nums">{page.total}</span>
            </p>

            {page.last_page > 1 && (
                <div className="flex items-center gap-1">
                    <Step
                        url={page.prev_page_url}
                        label="Previous"
                        icon={<ChevronLeft />}
                    />

                    {pageNumbers(page.current_page, page.last_page).map(
                        (entry, index) =>
                            entry === null ? (
                                <span
                                    // Ellipses have no identity of their own;
                                    // there are at most two and they never move
                                    // relative to their neighbours.
                                    key={`gap-${index}`}
                                    className="px-1 text-sm text-base-content/40"
                                >
                                    …
                                </span>
                            ) : (
                                <Button
                                    key={entry}
                                    size="sm"
                                    variant={
                                        entry === page.current_page
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    aria-label={`Page ${entry}`}
                                    aria-current={
                                        entry === page.current_page
                                            ? 'page'
                                            : undefined
                                    }
                                    asChild={entry !== page.current_page}
                                    className="tabular-nums"
                                >
                                    {entry === page.current_page ? (
                                        <span>{entry}</span>
                                    ) : (
                                        <Link
                                            href={pageUrl(page, entry)}
                                            preserveScroll
                                            preserveState
                                        >
                                            {entry}
                                        </Link>
                                    )}
                                </Button>
                            ),
                    )}

                    <Step
                        url={page.next_page_url}
                        label="Next"
                        icon={<ChevronRight />}
                        trailing
                    />
                </div>
            )}
        </div>
    );
}

/** Previous or Next, disabled at the ends rather than hidden. */
function Step({
    url,
    label,
    icon,
    trailing = false,
}: {
    url: string | null;
    label: string;
    icon: ReactNode;
    trailing?: boolean;
}) {
    const content = trailing ? (
        <>
            <span className="hidden sm:inline">{label}</span> {icon}
        </>
    ) : (
        <>
            {icon} <span className="hidden sm:inline">{label}</span>
        </>
    );

    return (
        <Button
            variant="secondary"
            size="sm"
            disabled={url === null}
            asChild={url !== null}
            aria-label={label}
        >
            {url === null ? (
                <span>{content}</span>
            ) : (
                <Link href={url} preserveScroll preserveState>
                    {content}
                </Link>
            )}
        </Button>
    );
}

/**
 * A page's URL, taken from a link the paginator already built.
 *
 * Building it by hand would mean re-deriving the query string, and
 * `withQueryString()` has already put every filter, the sort and the page size
 * onto these. `prev_page_url` is the reliable donor: it exists on every page but
 * the first, and the first is the one case that needs no derivation.
 */
function pageUrl<T>(page: Paginated<T>, target: number): string {
    const donor = page.prev_page_url ?? page.next_page_url ?? '';

    return donor.replace(/([?&]page=)\d+/, `$1${target}`);
}

/**
 * The page numbers to show, with `null` standing in for an elision.
 *
 * Always the first and last page, plus a window either side of the current one,
 * so the list keeps a constant width instead of growing with the result set.
 */
function pageNumbers(current: number, last: number): (number | null)[] {
    const wanted = new Set<number>([1, last]);

    for (let page = current - WINDOW; page <= current + WINDOW; page++) {
        if (page >= 1 && page <= last) {
            wanted.add(page);
        }
    }

    const sorted = [...wanted].sort((a, b) => a - b);
    const out: (number | null)[] = [];

    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            out.push(null);
        }

        out.push(page);
    });

    return out;
}
