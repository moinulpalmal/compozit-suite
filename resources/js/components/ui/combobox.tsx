import { useCombobox, useMultipleSelection } from 'downshift';
import { Check, ChevronsUpDown, LoaderCircle, Search, X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import { useOptionSearch } from '@/hooks/use-option-search';
import { ANCHOR_GAP, positionAnchored } from '@/lib/anchored-position';
import { cn } from '@/lib/utils';

/**
 * A searchable select, replacing every native `<select>` in the application.
 *
 * Built on `downshift`, which supplies the ARIA combobox semantics — roles,
 * `aria-activedescendant`, keyboard navigation and result announcements. Those
 * are the reason a package was bought rather than this being hand-rolled the way
 * `dropdown-menu.tsx` was; that file's own docblock records the rule.
 *
 * Three things are worth knowing before using it:
 *
 * 1. **It submits through a hidden `<input>`.** Every form in this app is an
 *    uncontrolled `<Form {...submit}>` reading `name=` off native elements, and a
 *    `<div role="combobox">` submits nothing. The hidden input is what keeps that
 *    model working — it is load-bearing, not a detail.
 * 2. **The search box only appears above {@link SEARCH_THRESHOLD} options.** A
 *    filter input in front of a two-option Active/Inactive choice is slower than
 *    the plain listbox it replaced, so short lists do not get one. One component,
 *    one constant, no per-call-site judgement.
 * 3. **`searchUrl` is opt-in.** Without it every option is filtered in the
 *    browser, which is right until a list outgrows being shipped whole.
 * 4. **`InputClick` is suppressed, in both variants.** downshift's own reducer
 *    treats a click on the input as `isOpen: !isOpen`, which is right when the
 *    input *is* the control and wrong here, where it lives inside the open menu:
 *    clicking the search box to type in it closed the menu. Every `useCombobox`
 *    below therefore needs a `stateReducer` holding `isOpen` steady for that one
 *    action type. Clicking the toggle button still closes the menu, and outside
 *    click and Escape still dismiss it — a menu holds no typed work, so it is
 *    deliberately not covered by the modal rule in `dialog.tsx`.
 */

/** Below this many options, no search input is rendered. */
export const SEARCH_THRESHOLD = 10;

export type ComboboxOption = {
    value: string | number;
    label: string;
    /** Secondary text shown after the label — a code, a status. */
    hint?: string;
    disabled?: boolean;
};

type SharedProps = {
    /** Name of the hidden input, i.e. the key the server reads. */
    name?: string;
    options: ComboboxOption[];
    placeholder?: string;
    /** Wayfinder URL taking `?q=`; enables server-side option search. */
    searchUrl?: string;
    id?: string;
    className?: string;
    disabled?: boolean;
    required?: boolean;
    'aria-label'?: string;
    'data-test'?: string;
};

type SingleProps = SharedProps & {
    multiple?: false;
    value?: string | number | null;
    defaultValue?: string | number | null;
    onChange?: (value: string | number | null) => void;
};

type MultipleProps = SharedProps & {
    multiple: true;
    value?: Array<string | number>;
    defaultValue?: Array<string | number>;
    onChange?: (value: Array<string | number>) => void;
};

export default function Combobox(props: SingleProps | MultipleProps) {
    return props.multiple === true ? (
        <MultiCombobox {...props} />
    ) : (
        <SingleCombobox {...props} />
    );
}

/* -------------------------------------------------------------------------- */
/* Single select                                                              */
/* -------------------------------------------------------------------------- */

function SingleCombobox({
    name,
    options,
    placeholder = 'Choose…',
    searchUrl,
    id,
    className,
    disabled = false,
    required = false,
    value,
    defaultValue = null,
    onChange,
    ...rest
}: SingleProps) {
    const [uncontrolled, setUncontrolled] = useState<string | number | null>(
        defaultValue,
    );
    const isControlled = value !== undefined;
    const selectedValue = isControlled ? value : uncontrolled;

    const [query, setQuery] = useState('');
    const remote = useOptionSearch(searchUrl, query);
    const visible = useVisibleOptions(
        options,
        query,
        remote.options,
        searchUrl,
    );

    /*
     * The selected option may not be in `visible` — it has been filtered out, or
     * an async page no longer contains it. Fall back to the full list so the
     * button keeps showing a label rather than going blank mid-search.
     */
    const selected =
        options.find((option) => option.value === selectedValue) ??
        remote.options.find((option) => option.value === selectedValue) ??
        null;

    const showSearch =
        searchUrl !== undefined || options.length >= SEARCH_THRESHOLD;

    const {
        isOpen,
        getToggleButtonProps,
        getMenuProps,
        getInputProps,
        getItemProps,
        highlightedIndex,
    } = useCombobox({
        items: visible,
        itemToString: (option) => option?.label ?? '',
        selectedItem: selected,
        inputValue: query,
        onInputValueChange: ({ inputValue }) => setQuery(inputValue ?? ''),
        onIsOpenChange: ({ isOpen: nowOpen }) => {
            if (!nowOpen) {
                setQuery('');
            }
        },
        onSelectedItemChange: ({ selectedItem }) => {
            const next = selectedItem?.value ?? null;

            if (!isControlled) {
                setUncontrolled(next);
            }

            onChange?.(next);
        },
        stateReducer: (state, { changes, type }) =>
            type === useCombobox.stateChangeTypes.InputClick
                ? { ...changes, isOpen: state.isOpen }
                : changes,
    });

    const { anchorRef, menuRef } = useAnchoredMenu(isOpen);
    const menuProps = getMenuProps({ ref: menuRef });
    const inputProps = getInputProps();

    /*
     * `id` goes *through* the prop getter rather than being spread before it:
     * downshift supplies its own, and letting that win would silently break
     * every `<Label htmlFor>` pointing at this control.
     */
    const toggleProps = getToggleButtonProps({ ref: anchorRef, id });

    return (
        <div className="relative">
            {name !== undefined && (
                <input
                    type="hidden"
                    name={name}
                    value={selectedValue ?? ''}
                    /* Mirrors the trigger so a missing required value still
                       blocks submission and reports on the right control. */
                    required={required}
                />
            )}

            <button
                type="button"
                disabled={disabled}
                // `className` styles the control, not the wrapper — the same
                // contract as `Input`, so `select-sm` and widths land where a
                // call site expects them to.
                className={cn(
                    'select w-full items-center justify-between text-left',
                    selected === null && 'text-base-content/50',
                    className,
                )}
                {...rest}
                {...toggleProps}
            >
                <span className="truncate">
                    {selected?.label ?? placeholder}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
            </button>

            <MenuShell
                menuProps={menuProps}
                showSearch={showSearch}
                inputProps={inputProps}
                loading={remote.loading}
                isOpen={isOpen}
            >
                {visible.length === 0 && <EmptyRow loading={remote.loading} />}

                {visible.map((option, index) => (
                    <OptionRow
                        key={option.value}
                        option={option}
                        highlighted={highlightedIndex === index}
                        selected={option.value === selectedValue}
                        {...getItemProps({ item: option, index })}
                    />
                ))}
            </MenuShell>
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Multi select                                                               */
/* -------------------------------------------------------------------------- */

function MultiCombobox({
    name,
    options,
    placeholder = 'Choose…',
    searchUrl,
    id,
    className,
    disabled = false,
    value,
    defaultValue,
    onChange,
    ...rest
}: MultipleProps) {
    const [uncontrolled, setUncontrolled] = useState<Array<string | number>>(
        defaultValue ?? [],
    );
    const isControlled = value !== undefined;
    const selectedValues = isControlled ? value : uncontrolled;

    const [query, setQuery] = useState('');
    const remote = useOptionSearch(searchUrl, query);

    const selectedItems = useMemo(() => {
        // Remote results are folded in so a chip keeps its label after the
        // option that produced it has been searched away.
        const pool =
            searchUrl === undefined ? options : [...options, ...remote.options];

        return selectedValues
            .map((entry) => pool.find((option) => option.value === entry))
            .filter((option): option is ComboboxOption => option !== undefined);
    }, [options, remote.options, searchUrl, selectedValues]);

    const commit = useCallback(
        (next: Array<string | number>) => {
            if (!isControlled) {
                setUncontrolled(next);
            }

            onChange?.(next);
        },
        [isControlled, onChange],
    );

    const filtered = useVisibleOptions(
        options,
        query,
        remote.options,
        searchUrl,
    );
    // Already-chosen options drop out of the menu rather than appearing ticked;
    // they are visible as chips directly above it.
    const visible = filtered.filter(
        (option) => !selectedValues.includes(option.value),
    );

    const { getSelectedItemProps, getDropdownProps, removeSelectedItem } =
        useMultipleSelection<ComboboxOption>({
            selectedItems,
            onSelectedItemsChange: ({ selectedItems: next }) =>
                commit((next ?? []).map((option) => option.value)),
        });

    const showSearch =
        searchUrl !== undefined || options.length >= SEARCH_THRESHOLD;

    const {
        isOpen,
        getToggleButtonProps,
        getMenuProps,
        getInputProps,
        getItemProps,
        highlightedIndex,
    } = useCombobox({
        items: visible,
        itemToString: (option) => option?.label ?? '',
        inputValue: query,
        // Multi-select keeps the menu open so several can be picked in a row.
        selectedItem: null,
        onInputValueChange: ({ inputValue }) => setQuery(inputValue ?? ''),
        onIsOpenChange: ({ isOpen: nowOpen }) => {
            if (!nowOpen) {
                setQuery('');
            }
        },
        onSelectedItemChange: ({ selectedItem }) => {
            if (selectedItem === null || selectedItem === undefined) {
                return;
            }

            commit([...selectedValues, selectedItem.value]);
        },
        stateReducer: (state, { changes, type }) => {
            if (type === useCombobox.stateChangeTypes.InputClick) {
                return { ...changes, isOpen: state.isOpen };
            }

            return type === useCombobox.stateChangeTypes.ItemClick ||
                type === useCombobox.stateChangeTypes.InputKeyDownEnter
                ? { ...changes, isOpen: true, inputValue: '' }
                : changes;
        },
    });

    const { anchorRef, menuRef } = useAnchoredMenu(isOpen);
    const menuProps = getMenuProps({ ref: menuRef });
    const inputProps = getInputProps(
        getDropdownProps({ preventKeyAction: isOpen }),
    );

    /* See the note in SingleCombobox — downshift's own `id` must not win. */
    const toggleProps = getToggleButtonProps({ ref: anchorRef, id });

    return (
        <div className="relative">
            {/* One hidden input per selection, so the server receives an array
                exactly as a checkbox list would have sent it. */}
            {name !== undefined &&
                selectedValues.map((entry) => (
                    <input
                        key={entry}
                        type="hidden"
                        name={`${name}[]`}
                        value={entry}
                    />
                ))}

            <button
                type="button"
                disabled={disabled}
                className={cn(
                    'select h-auto min-h-10 w-full items-center justify-between gap-2 py-1.5 text-left',
                    className,
                )}
                {...rest}
                {...toggleProps}
            >
                <span className="flex flex-wrap items-center gap-1">
                    {selectedItems.length === 0 && (
                        <span className="text-base-content/50">
                            {placeholder}
                        </span>
                    )}

                    {selectedItems.map((option, index) => (
                        <span
                            key={option.value}
                            className="badge gap-1 badge-ghost badge-sm"
                            {...getSelectedItemProps({
                                selectedItem: option,
                                index,
                            })}
                        >
                            {option.label}
                            <span
                                role="button"
                                tabIndex={-1}
                                aria-label={`Remove ${option.label}`}
                                className="cursor-pointer opacity-60 hover:opacity-100"
                                onClick={(event) => {
                                    // Otherwise the toggle button opens the menu.
                                    event.stopPropagation();
                                    removeSelectedItem(option);
                                }}
                            >
                                <X className="size-3" />
                            </span>
                        </span>
                    ))}
                </span>

                <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
            </button>

            <MenuShell
                menuProps={menuProps}
                showSearch={showSearch}
                inputProps={inputProps}
                loading={remote.loading}
                isOpen={isOpen}
            >
                {visible.length === 0 && <EmptyRow loading={remote.loading} />}

                {visible.map((option, index) => (
                    <OptionRow
                        key={option.value}
                        option={option}
                        highlighted={highlightedIndex === index}
                        selected={false}
                        {...getItemProps({ item: option, index })}
                    />
                ))}
            </MenuShell>
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Shared pieces                                                              */
/* -------------------------------------------------------------------------- */

/**
 * The floating listbox, in the top layer so it escapes `overflow` containers.
 *
 * `popover="manual"` rather than `"auto"`: light dismiss would close the menu
 * behind downshift's back and desynchronise its `isOpen` state, so opening and
 * closing are driven from that state instead.
 */
function MenuShell({
    menuProps,
    showSearch,
    inputProps,
    loading,
    isOpen,
    children,
}: {
    menuProps: Record<string, unknown>;
    showSearch: boolean;
    inputProps: Record<string, unknown>;
    loading: boolean;
    isOpen: boolean;
    children: React.ReactNode;
}) {
    return (
        <div
            popover="manual"
            className={cn(
                'fixed inset-auto z-50 m-0 w-(--anchor-width) min-w-56 rounded-box bg-base-100 p-1 shadow-lg ring-1 ring-base-300',
                !isOpen && 'hidden',
            )}
            {...(menuProps as Record<string, never>)}
        >
            {/* The input stays mounted when hidden: downshift owns its ref and
                keyboard handlers, and unmounting it would break arrow keys on
                short lists. */}
            <div className={cn('p-1', !showSearch && 'sr-only')}>
                <label className="input w-full input-sm">
                    <Search className="size-4 opacity-50" />
                    <input
                        {...(inputProps as Record<string, never>)}
                        placeholder="Search…"
                    />
                    {loading && (
                        <LoaderCircle className="size-4 animate-spin opacity-50" />
                    )}
                </label>
            </div>

            <ul className="max-h-64 overflow-y-auto">{children}</ul>
        </div>
    );
}

function OptionRow({
    option,
    highlighted,
    selected,
    ...props
}: {
    option: ComboboxOption;
    highlighted: boolean;
    selected: boolean;
} & Record<string, unknown>) {
    return (
        <li
            className={cn(
                'flex cursor-pointer items-center justify-between gap-2 rounded-field px-3 py-1.5 text-sm',
                highlighted && 'bg-base-200',
                option.disabled && 'cursor-not-allowed opacity-50',
            )}
            {...(props as Record<string, never>)}
        >
            <span className="truncate">
                {option.label}
                {option.hint !== undefined && (
                    <span className="ml-2 font-mono text-xs text-base-content/50">
                        {option.hint}
                    </span>
                )}
            </span>

            {selected && <Check className="size-4 shrink-0" />}
        </li>
    );
}

function EmptyRow({ loading }: { loading: boolean }) {
    return (
        <li className="px-3 py-2 text-sm text-base-content/60">
            {loading ? 'Searching…' : 'No matches.'}
        </li>
    );
}

/**
 * Which options the menu shows: the server's results when searching remotely,
 * otherwise a local case-insensitive match on label and hint.
 */
function useVisibleOptions(
    options: ComboboxOption[],
    query: string,
    remoteOptions: ComboboxOption[],
    searchUrl?: string,
): ComboboxOption[] {
    return useMemo(() => {
        if (searchUrl !== undefined) {
            /*
             * Fall back to the options the page was rendered with until the
             * first remote result lands. Without this, opening the menu shows
             * an empty list for the length of the debounce every time.
             */
            return remoteOptions.length === 0 && query.trim() === ''
                ? options
                : remoteOptions;
        }

        const term = query.trim().toLowerCase();

        if (term === '') {
            return options;
        }

        return options.filter(
            (option) =>
                option.label.toLowerCase().includes(term) ||
                (option.hint ?? '').toLowerCase().includes(term),
        );
    }, [options, query, remoteOptions, searchUrl]);
}

/**
 * Show the popover while open, and keep it pinned to its trigger.
 *
 * Repositioning on scroll and resize is what `position: fixed` in the top layer
 * costs; see `lib/anchored-position.ts`.
 */
function useAnchoredMenu(isOpen: boolean) {
    const anchorRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const anchor = anchorRef.current;
        const menu = menuRef.current;

        if (!anchor || !menu) {
            return;
        }

        if (!isOpen) {
            if (menu.matches(':popover-open')) {
                menu.hidePopover();
            }

            return;
        }

        if (!menu.matches(':popover-open')) {
            menu.showPopover();
        }

        const place = (): void =>
            positionAnchored(anchor, menu, { sideOffset: ANCHOR_GAP });

        place();

        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [isOpen]);

    return { anchorRef, menuRef };
}

/** Stable ids for call sites that need to label the control themselves. */
export function useComboboxId(provided?: string): string {
    const generated = useId();

    return provided ?? generated;
}
