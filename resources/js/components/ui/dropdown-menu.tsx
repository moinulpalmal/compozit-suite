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
import { cn } from '@/lib/utils';

/**
 * A menu built on the Popover API and daisyUI's `menu`, replacing Radix's
 * DropdownMenu.
 *
 * `popover="auto"` gives us the top layer (so the menu escapes the collapsed
 * sidebar's `overflow: hidden` without a portal), light dismiss on Escape and
 * outside click, and focus restored to the trigger. Two things it does not give
 * are written by hand below: arrow-key roving focus, and closing when Tab moves
 * focus out of the menu.
 *
 * Placement is computed in JS rather than with CSS anchor positioning: it needs no
 * support guard, and clamping to the viewport replaces the collision handling that
 * came free with Radix.
 *
 * Deliberately not supported: typeahead, submenus, and checkbox/radio items — none
 * are used here. If they are ever needed, reinstate `@radix-ui/react-dropdown-menu`
 * and restyle it rather than growing this file.
 */
type Side = 'top' | 'right' | 'bottom' | 'left';
type Align = 'start' | 'center' | 'end';

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

const VIEWPORT_MARGIN = 8;
const TRIGGER_GAP = 4;

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
    sideOffset = TRIGGER_GAP,
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

        const anchor = trigger.getBoundingClientRect();
        const menu = content.getBoundingClientRect();

        content.style.setProperty(
            '--dropdown-trigger-width',
            `${anchor.width}px`,
        );

        let top: number;
        let left: number;

        if (side === 'top' || side === 'bottom') {
            top =
                side === 'bottom'
                    ? anchor.bottom + sideOffset
                    : anchor.top - menu.height - sideOffset;

            left =
                align === 'end'
                    ? anchor.right - menu.width
                    : align === 'center'
                      ? anchor.left + (anchor.width - menu.width) / 2
                      : anchor.left;

            // Flip when the preferred side has no room but the opposite one does.
            if (
                side === 'bottom' &&
                top + menu.height > window.innerHeight - VIEWPORT_MARGIN &&
                anchor.top - menu.height - sideOffset > VIEWPORT_MARGIN
            ) {
                top = anchor.top - menu.height - sideOffset;
            } else if (
                side === 'top' &&
                top < VIEWPORT_MARGIN &&
                anchor.bottom + menu.height + sideOffset <
                    window.innerHeight - VIEWPORT_MARGIN
            ) {
                top = anchor.bottom + sideOffset;
            }
        } else {
            left =
                side === 'right'
                    ? anchor.right + sideOffset
                    : anchor.left - menu.width - sideOffset;

            top =
                align === 'end'
                    ? anchor.bottom - menu.height
                    : align === 'center'
                      ? anchor.top + (anchor.height - menu.height) / 2
                      : anchor.top;

            if (
                side === 'right' &&
                left + menu.width > window.innerWidth - VIEWPORT_MARGIN &&
                anchor.left - menu.width - sideOffset > VIEWPORT_MARGIN
            ) {
                left = anchor.left - menu.width - sideOffset;
            } else if (
                side === 'left' &&
                left < VIEWPORT_MARGIN &&
                anchor.right + menu.width + sideOffset <
                    window.innerWidth - VIEWPORT_MARGIN
            ) {
                left = anchor.right + sideOffset;
            }
        }

        content.style.left = `${clamp(left, menu.width, window.innerWidth)}px`;
        content.style.top = `${clamp(top, menu.height, window.innerHeight)}px`;
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
                'menu dropdown-content fixed inset-auto z-50 m-0 min-w-40 gap-0.5 rounded-box bg-base-100 p-1.5 shadow-lg ring-1 ring-base-300',
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
                    'flex cursor-pointer items-center gap-2 text-sm',
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
                'menu-title px-2 py-1.5 text-sm font-medium',
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

function clamp(value: number, size: number, viewport: number): number {
    const max = Math.max(VIEWPORT_MARGIN, viewport - size - VIEWPORT_MARGIN);

    return Math.min(Math.max(VIEWPORT_MARGIN, value), max);
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
