import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/**
 * Previous/next paging with a "showing x–y of z" read-out.
 *
 * Lives in `components/admin/` because Admin is currently its only consumer —
 * move it to `components/shared/` the moment a second module imports it, per
 * ARCHITECTURE.md §6.5.
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
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={page.prev_page_url === null}
                        asChild={page.prev_page_url !== null}
                    >
                        {page.prev_page_url === null ? (
                            <span>
                                <ChevronLeft /> Previous
                            </span>
                        ) : (
                            <Link
                                href={page.prev_page_url}
                                preserveScroll
                                preserveState
                            >
                                <ChevronLeft /> Previous
                            </Link>
                        )}
                    </Button>

                    <span className="text-sm text-base-content/60 tabular-nums">
                        {page.current_page} / {page.last_page}
                    </span>

                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={page.next_page_url === null}
                        asChild={page.next_page_url !== null}
                    >
                        {page.next_page_url === null ? (
                            <span>
                                Next <ChevronRight />
                            </span>
                        ) : (
                            <Link
                                href={page.next_page_url}
                                preserveScroll
                                preserveState
                            >
                                Next <ChevronRight />
                            </Link>
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
