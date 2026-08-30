import type { ComboboxOption } from '@/components/ui/combobox';
import Combobox from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import type { FilterMatch, Filterable } from '@/types';

/** A text box filtering one column. */
export type TextCell = {
    type: 'text';
    column: string;
    /** Used in the cell's accessible name: "Filter by {label}". */
    label: string;
    /**
     * Overrides the match-type placeholder.
     *
     * Worth setting only inside a `stack`, where several boxes share one column
     * heading and need naming individually.
     */
    placeholder?: string;
};

/** A dropdown filtering one column against a fixed option list. */
export type SelectCell = {
    type: 'select';
    column: string;
    label: string;
    options: ComboboxOption[];
    testId?: string;
};

/**
 * One cell of the filter row.
 *
 * `none` is explicit rather than implied by omission: the row has to line up
 * with the header row column for column, so a column with nothing to filter
 * still has to claim its `<td>`.
 *
 * `stack` exists because a table column is not always one database column — the
 * users list shows the email under the name and two numbers under "Contact".
 */
export type FilterCell =
    | (TextCell & { className?: string })
    | (SelectCell & { className?: string })
    | { type: 'stack'; cells: TextCell[]; className?: string }
    | { type: 'none'; className?: string };

/**
 * The row of filter cells under an Admin list's headers.
 *
 * Each cell filters its own column and the cells are `AND`-ed, which is the
 * shape a database can actually serve — `OR`-ing one term across every column
 * is the one that cannot be indexed (ARCHITECTURE.md §6.3).
 *
 * Text cells push on a debounce, dropdowns visit immediately; both go through
 * `useListFilters`. Placeholders differ by match type on purpose, because the
 * difference is visible: on a `prefix` column "868" will not find employee
 * 15868, and a row of identical-looking boxes would make that read as a bug.
 */
export default function ColumnFilterRow({
    cells,
    filterable,
    draft,
    onFilter,
}: {
    cells: FilterCell[];
    /** The model's `FILTERABLE` map — supplies each cell's match type. */
    filterable: Filterable;
    /** Current cell values, held by `useListFilters` while typing. */
    draft: Record<string, string>;
    onFilter: (column: string, value: string, debounce: boolean) => void;
}) {
    const text = (cell: TextCell) => (
        <Input
            key={cell.column}
            type="search"
            className="w-full min-w-24 input-xs"
            value={draft[cell.column] ?? ''}
            onChange={(event) =>
                onFilter(cell.column, event.target.value, true)
            }
            placeholder={
                cell.placeholder ?? placeholder(filterable[cell.column])
            }
            aria-label={`Filter by ${cell.label}`}
        />
    );

    return (
        <tr className="bg-base-200/40">
            {cells.map((cell, index) => (
                <td
                    // Cells without a column have nothing else to key on, and
                    // the row is a fixed list that never reorders.
                    key={
                        cell.type === 'none' || cell.type === 'stack'
                            ? `${cell.type}-${index}`
                            : cell.column
                    }
                    className={cell.className}
                >
                    {cell.type === 'text' && text(cell)}

                    {cell.type === 'stack' && (
                        <div className="grid gap-1">{cell.cells.map(text)}</div>
                    )}

                    {cell.type === 'select' && (
                        <Combobox
                            className="w-full min-w-28 select-xs"
                            aria-label={`Filter by ${cell.label}`}
                            data-test={cell.testId}
                            value={draft[cell.column] ?? ''}
                            onChange={(value) =>
                                onFilter(
                                    cell.column,
                                    String(value ?? ''),
                                    false,
                                )
                            }
                            options={cell.options}
                        />
                    )}
                </td>
            ))}
        </tr>
    );
}

/**
 * What the cell says it will do, in the space a cell has.
 *
 * `scope` is a derived filter rather than a column, and the only one today
 * (`Permission::scopeModule()`) already matches a whole segment, so it reads as
 * a prefix to the person typing.
 */
function placeholder(match: FilterMatch | undefined): string {
    return match === 'prefix' || match === 'scope'
        ? 'Starts with…'
        : 'Contains…';
}
