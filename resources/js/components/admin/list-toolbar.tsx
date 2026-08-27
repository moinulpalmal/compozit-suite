import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ComboboxOption } from '@/components/ui/combobox';
import Combobox from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import type { ListFilters } from '@/types';

/**
 * One filter dropdown on the toolbar.
 *
 * Each control carries its own value and handler rather than being keyed into
 * the filter object, so a page wires its filters explicitly and stays type-safe
 * about which keys it actually has.
 */
export type FilterControl = {
    label: string;
    ariaLabel: string;
    value: string;
    options: ComboboxOption[];
    onSelect: (value: string) => void;
    /** Tailwind width for the control, e.g. `w-52`. */
    width?: string;
    testId?: string;
};

/**
 * The filter bar every Admin list screen shares: dropdown filters, then a
 * field-scoped search.
 *
 * Search is scoped to **one** column on purpose. Matching a term against every
 * column with `OR` is the shape MySQL cannot index — see ARCHITECTURE.md §6.3.
 * Matching is by prefix, which the placeholder says out loud so nobody files a
 * bug about "868" not finding employee 15868.
 *
 * Generalised from `users-table-toolbar.tsx` when designations, roles and
 * permissions all became searchable lists (§8.6).
 */
export default function ListToolbar({
    filters,
    searchable,
    searchLabels = {},
    controls = [],
    onChange,
    onClear,
}: {
    filters: ListFilters;
    /** Field names the server allow-lists for search. */
    searchable: string[];
    /** Human labels for those field names; the raw name is the fallback. */
    searchLabels?: Record<string, string>;
    controls?: FilterControl[];
    onChange: (next: Partial<ListFilters>) => void;
    /**
     * Reset every filter in **one** visit. Calling each control's `onSelect`
     * in turn would issue one request per filter.
     */
    onClear: () => void;
}) {
    const [term, setTerm] = useState(filters.search);
    const [field, setField] = useState(filters.search_field);

    const label = (name: string): string => searchLabels[name] ?? name;

    const hasActiveFilter =
        filters.search !== '' ||
        controls.some((control) => control.value !== '');

    return (
        <div className="flex flex-wrap items-end gap-3">
            {/* Comboboxes, not `<select>`s — ARCHITECTURE.md §8.5. Each renders
                as a plain listbox until its list passes SEARCH_THRESHOLD, so a
                two-option filter stays one click. */}
            {controls.map((control) => (
                <Field key={control.label} label={control.label}>
                    <Combobox
                        className={`select-sm ${control.width ?? 'w-44'}`}
                        aria-label={control.ariaLabel}
                        data-test={control.testId}
                        value={control.value}
                        onChange={(value) =>
                            control.onSelect(String(value ?? ''))
                        }
                        options={control.options}
                    />
                </Field>
            ))}

            <form
                className="flex items-end gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    onChange({ search: term, search_field: field });
                }}
            >
                {searchable.length > 1 && (
                    <Field label="Search in">
                        <Combobox
                            className="w-44 select-sm"
                            aria-label="Field to search"
                            value={field}
                            onChange={(value) =>
                                setField(
                                    value === '' || value === null
                                        ? searchable[0]
                                        : String(value),
                                )
                            }
                            options={searchable.map((name) => ({
                                value: name,
                                label: label(name),
                            }))}
                        />
                    </Field>
                )}

                <Input
                    type="search"
                    className="w-56 input-sm"
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                    placeholder={`Starts with… (${label(field)})`}
                    aria-label="Search term"
                />

                <Button type="submit" size="sm" variant="secondary">
                    <Search /> Search
                </Button>

                {hasActiveFilter && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setTerm('');
                            onClear();
                        }}
                    >
                        Clear
                    </Button>
                )}
            </form>
        </div>
    );
}

/**
 * A labelled control.
 *
 * A plain `<div>` rather than a `<label>`: a combobox is a button plus a hidden
 * input, so implicit label association no longer works. Each control carries an
 * `aria-label` instead.
 */
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1">
            <span className="text-xs text-base-content/60">{label}</span>
            {children}
        </div>
    );
}
