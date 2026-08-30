import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import type { ListFilters } from '@/types';

/**
 * A `<th>` that sorts the list, or a plain one when the column is not
 * allow-listed by the server.
 *
 * Extracted from `pages/admin/users/index.tsx` when every Admin list became
 * sortable — see ARCHITECTURE.md §8.6. It stayed in `components/admin/` while
 * only Admin surfaces used it, because §6.5's promotion rule triggers on a
 * second *module* importing it and not on a second surface; Settings
 * master-data was that second module.
 */
export default function SortableHeader({
    column,
    sortable,
    filters,
    onSort,
    className,
    children,
}: {
    column: string;
    /** Columns the server will accept; anything else renders unclickable. */
    sortable: string[];
    filters: ListFilters;
    onSort: (column: string) => void;
    className?: string;
    children: ReactNode;
}) {
    if (!sortable.includes(column)) {
        return <th className={className}>{children}</th>;
    }

    const active = filters.sort === column;
    const Icon = !active
        ? ArrowUpDown
        : filters.direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <th
            className={className}
            aria-sort={
                active
                    ? filters.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                className="inline-flex cursor-pointer items-center gap-1 hover:text-base-content"
                onClick={() => onSort(column)}
                data-test={`sort-${column}`}
            >
                {children}
                <Icon className={active ? 'size-3' : 'size-3 opacity-40'} />
            </button>
        </th>
    );
}

/**
 * Flip direction on the active column, start ascending on a new one.
 *
 * The same three lines were about to appear on four pages.
 */
export function nextSort(
    filters: ListFilters,
    column: string,
): { sort: string; direction: 'asc' | 'desc' } {
    return {
        sort: column,
        direction:
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc',
    };
}
