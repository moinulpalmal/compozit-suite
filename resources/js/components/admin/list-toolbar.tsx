import { FilterX } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import Combobox from '@/components/ui/combobox';

/**
 * The thin bar above an Admin list.
 *
 * It used to hold the search box and every dropdown filter. Those are now cells
 * in `ColumnFilterRow`, under the header of the column they filter, so what is
 * left here is only what is **not** a column: how many rows to show, a way to
 * empty the whole row at once, and whatever record-set switch a surface has
 * (the users list's Active/Historical tabs pass through `children`).
 *
 * Clearing is one visit, not one per cell — calling each cell's handler in turn
 * would fire a request per filter.
 */
export default function ListToolbar({
    perPage,
    perPageOptions,
    onPerPage,
    onClear,
    hasActiveFilter,
    children,
}: {
    perPage: number;
    perPageOptions: number[];
    onPerPage: (perPage: number) => void;
    onClear: () => void;
    /** Whether anything is filtered — the Clear button appears only then. */
    hasActiveFilter: boolean;
    /** Surface extras, e.g. the users list's Active/Historical tabs. */
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-3">{children}</div>

            <div className="flex items-center gap-2">
                {hasActiveFilter && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onClear}
                        data-test="clear-filters"
                    >
                        <FilterX /> Clear filters
                    </Button>
                )}

                <span className="text-xs text-base-content/60">Rows</span>

                <Combobox
                    className="w-20 select-sm"
                    aria-label="Rows per page"
                    data-test="per-page"
                    value={String(perPage)}
                    /* A cleared combobox emits null, and `Number(null)` is 0 —
                       which the server rejects. Keep the current size instead. */
                    onChange={(value) => onPerPage(Number(value) || perPage)}
                    options={perPageOptions.map((size) => ({
                        value: String(size),
                        label: String(size),
                    }))}
                />
            </div>
        </div>
    );
}
