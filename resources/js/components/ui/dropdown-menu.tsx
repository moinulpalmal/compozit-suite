import { Slot } from '@radix-ui/react-slot';
import type { ComponentProps, ReactNode, RefObject } from 'react';
import {
    createContext,
    use,
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import type { Align, Side } from '@/lib/anchored-position';
import { ANCHOR_GAP, positionAnchored } from '@/lib/anchored-position';
import { cn } from '@/lib/utils';

/**
 * A menu built on the Popover API, replacing Radix's DropdownMenu.
 *
 * It is styled with plain utilities rather than daisyUI's `menu`, which it used to
 * wear — see the warning below for why that is not a cosmetic preference.
 *
 * `popover="auto"` gives us the top layer (so the menu escapes the collapsed
 * sidebar's `overflow: hidden` without a portal), light dismiss on Escape and
 * outside click, and focus restored to the trigger. Two things it does not give
 * are written by hand below: arrow-key roving focus, and closing when Tab moves
 * focus out of the menu.
 *
 * Placement lives in `lib/anchored-position.ts`, shared with `combobox.tsx`.
 *
 * **Never put a class that sets `display` on the popover element.** The browser hides
 * a closed popover with a User-Agent rule — `[popover]:not(:popover-open){display:none}`
 * — and *any* author declaration beats it, because specificity and `@layer` only order
 * rules within one origin. daisyUI's `.menu{display:flex}` was on this element and the
 * menu could not be dismissed at all: `hidePopover()` ran, `:popover-open` went false,
 * and it stayed on screen through Escape, outside-click and a second trigger click, in
 * every engine. That is why the layout below is block flow with `space-y-*` rather than
 * `flex flex-col`, and why the daisyUI menu styling is inlined onto the items instead:
 * `.menu` cannot be worn here, so nothing may depend on it. `combobox.tsx` sidesteps the
 * same trap by gating its own `hidden` class on React state.
 *
 * Deliberately not supported: typeahead, submenus, and checkbox/radio items — none
 * are used here. If they are ever needed, reinstate `@radix-ui/react-dropdown-menu`
 * and restyle it rather than growing this file. `combobox.tsx` is where that rule
 * was applied: a searchable listbox was bought (`downshift`) rather than grown here.
 */

type DropdownMenuContextValue = {
    contentId: string;
    open: boolean;
    setOpen: (open: boolean) => void;
    triggerRef: RefObject<HTMLButtonElement | null>;
    contentRef: RefObject<HTMLUListElement | null>;
    openedByKeyboardRef: RefObject<boolean>;
    close: () => void;
};

const DropdownMenuContext = createContext<DropdownMenuContextValue | null>(
    null,
);

function useDropdownMenu(): DropdownMenuContextValue {
    const context = use(DropdownMenuContext);

    if (!context) {
        throw new Error(
            'DropdownMenu parts must be rendered inside <DropdownMenu>.',
        );
    }

    return context;
}

function DropdownMenu({ children }: { children?: ReactNode }) {
    const contentId = useId();
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const contentRef = useRef<HTMLUListElement>(null);
    const openedByKeyboardRef = useRef(false);

    const close = useCallback(() => {
        contentRef.current?.hidePopover();
    }, []);

    return (
        <DropdownMenuContext
            value={{
                contentId,
                open,
                setOpen,
                triggerRef,
                contentRef,
                openedByKeyboardRef,
                close,
            }}
        >
            {children}
        </DropdownMenuContext>
    );
}

function DropdownMenuTrigger({
    asChild = false,
    onClick,
    ...props
}: ComponentProps<'button'> & { asChild?: boolean }) {
    const { contentId, open, triggerRef, openedByKeyboardRef } =
        useDropdownMenu();
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            ref={triggerRef}
            data-slot="dropdown-menu-trigger"
            data-state={open ? 'open' : 'closed'}
            aria-haspopup="menu"
            aria-expanded={open}
            popoverTarget={contentId}
            onClick={(event) => {
                // A click synthesised from Enter or Space reports detail 0.
                openedByKeyboardRef.current = event.detail === 0;
                onClick?.(event);
            }}
            {...props}
        />
    );
}

type DropdownMenuContentProps = ComponentProps<'ul'> & {
    side?: Side;
    align?: Align;
    sideOffset?: number;
};

function DropdownMenuContent({
    className,
    children,
    side = 'bottom',
    align = 'start',
    sideOffset = ANCHOR_GAP,
    ...props
}: DropdownMenuContentProps) {
    const { contentId, contentRef, triggerRef, setOpen, openedByKeyboardRef } =
        useDropdownMenu();

    const position = useCallback(() => {
        const trigger = triggerRef.current;
        const content = contentRef.current;

        if (!trigger || !content) {
            return;
        }

        positionAnchored(trigger, content, {
            side,
            align,
            sideOffset,
            // `nav-user.tsx` widens its menu to the trigger with this.
            widthVar: '--dropdown-trigger-width',
        });
    }, [align, contentRef, side, sideOffset, triggerRef]);

    useEffect(() => {
        const content = contentRef.current;

        if (!content) {
            return;
        }

        const handleToggle = (event: Event): void => {
            const isOpen = (event as ToggleEvent).newState === 'open';

            setOpen(isOpen);

            if (!isOpen) {
                return;
            }

            position();

            if (openedByKeyboardRef.current) {
                focusItem(content, 0);
            }
        };

        const handleReposition = (): void => {
            if (content.matches(':popover-open')) {
                position();
            }
        };

        content.addEventListener('toggle', handleToggle);
        window.addEventListener('resize', handleReposition);
        window.addEventListener('scroll', handleReposition, true);

        return () => {
            content.removeEventListener('toggle', handleToggle);
            window.removeEventListener('resize', handleReposition);
            window.removeEventListener('scroll', handleReposition, true);
        };
    }, [contentRef, openedByKeyboardRef, position, setOpen]);

    return (
        <ul
            ref={contentRef}
            id={contentId}
            popover="auto"
            role="menu"
            data-slot="dropdown-menu-content"
            className={cn(
                // No `display` utility here, and none may be added — see the note above.
                'fixed inset-auto z-50 m-0 min-w-40 space-y-0.5 rounded-box bg-base-100 p-1.5 text-sm shadow-lg ring-1 ring-base-300',
                className,
            )}
            onKeyDown={(event) => handleMenuKeyDown(event.nativeEvent)}
            onBlur={(event) => {
                // Light dismiss does not fire when Tab moves focus out of the menu.
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    event.currentTarget.hidePopover();
                }
            }}
            {...props}
        >
            {children}
        </ul>
    );
}

function DropdownMenuItem({
    asChild = false,
    className,
    inset,
    variant = 'default',
    onClick,
    ...props
}: ComponentProps<'button'> & {
    asChild?: boolean;
    inset?: boolean;
    variant?: 'default' | 'destructive';
}) {
    const { close } = useDropdownMenu();
    const Comp = asChild ? Slot : 'button';

    return (
        <li role="none">
            <Comp
                role="menuitem"
                tabIndex={-1}
                data-slot="dropdown-menu-item"
                data-dropdown-item=""
                data-inset={inset}
                data-variant={variant}
                className={cn(
                    /*
                     * Padding, radius and transition were daisyUI's, from
                     * `.menu :where(li:not(.menu-title)>:not(ul,menu,details,.menu-title,.btn))`.
                     * That rule died with `.menu`, so the values it supplied are spelled
                     * out here. The hover background is new — daisyUI 5.7's `.menu` has
                     * none, so the items never highlighted.
                     */
                    'flex w-full cursor-pointer items-center gap-2 rounded-field px-3 py-1.5 text-start text-sm transition-colors select-none',
                    'hover:bg-base-200 focus-visible:bg-base-200 focus-visible:outline-none',
                    inset && 'pl-8',
                    variant === 'destructive' && 'text-error',
                    className,
                )}
                onClick={(event) => {
                    onClick?.(event);
                    close();
                }}
                {...props}
            />
        </li>
    );
}

function DropdownMenuLabel({
    className,
    inset,
    ...props
}: ComponentProps<'li'> & { inset?: boolean }) {
    return (
        <li
            role="presentation"
            data-slot="dropdown-menu-label"
            className={cn(
                /*
                 * Not `menu-title`: daisyUI fades it to
                 * `color-mix(in oklab, var(--color-base-content) 40%, transparent)`,
                 * which cascaded onto the avatar and name in the user menu's header
                 * and rendered both washed out against the row below.
                 */
                'px-2 py-1.5 text-sm font-medium',
                inset && 'pl-8',
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuSeparator({ className, ...props }: ComponentProps<'li'>) {
    return (
        <li
            role="separator"
            aria-orientation="horizontal"
            data-slot="dropdown-menu-separator"
            className={cn('-mx-1.5 my-1 border-t border-base-300', className)}
            {...props}
        />
    );
}

function menuItems(content: HTMLElement): HTMLElement[] {
    return Array.from(
        content.querySelectorAll<HTMLElement>(
            '[data-dropdown-item]:not([aria-disabled="true"]):not([disabled])',
        ),
    );
}

function focusItem(content: HTMLElement, index: number): void {
    const items = menuItems(content);

    if (items.length === 0) {
        return;
    }

    const target = (index + items.length) % items.length;

    items[target].focus();
}

function handleMenuKeyDown(event: KeyboardEvent): void {
    const content = event.currentTarget as HTMLElement | null;

    if (!content) {
        return;
    }

    const items = menuItems(content);
    const current = items.indexOf(document.activeElement as HTMLElement);

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            focusItem(content, current + 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            focusItem(content, current < 0 ? -1 : current - 1);
            break;
        case 'Home':
            event.preventDefault();
            focusItem(content, 0);
            break;
        case 'End':
            event.preventDefault();
            focusItem(content, items.length - 1);
            break;
    }
}

export {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
};
