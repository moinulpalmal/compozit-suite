import { Slot } from '@radix-ui/react-slot';
import { XIcon } from 'lucide-react';
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
 * A native `<dialog>` opened with `showModal()`, styled with daisyUI's `modal`.
 *
 * The browser supplies everything Radix's Dialog was doing: the top layer, a
 * focus trap, `inert` on the rest of the page, and returning focus to whatever
 * opened it. daisyUI adds the surface and the scroll lock (`--page-scroll-lock`),
 * and paints its own backdrop, hiding `::backdrop`.
 *
 * **A modal here never light-dismisses.** Both of the browser's escape routes are
 * refused: there is no `.modal-backdrop` form, so a backdrop click does nothing,
 * and `cancel` is `preventDefault()`ed, so Escape does nothing. Every dialog in
 * this application holds either typed work or a destructive confirmation, and a
 * stray click outside one was throwing that away. Menus are the opposite case and
 * keep their light dismiss — `dropdown-menu.tsx` and `combobox.tsx` are
 * deliberately not covered by this rule; see [§8.7](../../../../ARCHITECTURE.md).
 *
 * That makes the close button the *only* way out, which is why it is not optional
 * — there is no `showCloseButton` prop to turn it off with.
 *
 * **Escape is refused twice, not forever.** Chrome's close watcher lets a page
 * cancel a limited run of close requests and then forces one through: the first
 * two Escapes do nothing, the third closes the panel, and clicking inside first
 * does not reset the count (measured, not guessed). That backstop cannot be
 * disabled from script, and it is the browser guaranteeing a user is never
 * trapped. Treat one Escape doing nothing as the behaviour this rule buys.
 */
type DialogContextValue = {
    open: boolean;
    setOpen: (open: boolean) => void;
    dialogRef: RefObject<HTMLDialogElement | null>;
    titleId: string;
    descriptionId: string;
};

const DialogContext = createContext<DialogContextValue | null>(null);

function useDialog(): DialogContextValue {
    const context = use(DialogContext);

    if (!context) {
        throw new Error('Dialog parts must be rendered inside <Dialog>.');
    }

    return context;
}

type DialogProps = {
    children?: ReactNode;
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
};

function Dialog({
    children,
    open: openProp,
    defaultOpen = false,
    onOpenChange,
}: DialogProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(defaultOpen);
    const dialogRef = useRef<HTMLDialogElement>(null);
    const id = useId();

    const isControlled = openProp !== undefined;
    const open = isControlled ? openProp : uncontrolledOpen;

    const setOpen = useCallback(
        (next: boolean) => {
            if (!isControlled) {
                setUncontrolledOpen(next);
            }

            onOpenChange?.(next);
        },
        [isControlled, onOpenChange],
    );

    return (
        <DialogContext
            value={{
                open,
                setOpen,
                dialogRef,
                titleId: `${id}-title`,
                descriptionId: `${id}-description`,
            }}
        >
            {children}
        </DialogContext>
    );
}

function DialogTrigger({
    asChild = false,
    onClick,
    ...props
}: ComponentProps<'button'> & { asChild?: boolean }) {
    const { open, setOpen } = useDialog();
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="dialog-trigger"
            aria-haspopup="dialog"
            aria-expanded={open}
            onClick={(event) => {
                onClick?.(event);
                setOpen(true);
            }}
            {...props}
        />
    );
}

function DialogClose({
    asChild = false,
    onClick,
    ...props
}: ComponentProps<'button'> & { asChild?: boolean }) {
    const { setOpen } = useDialog();
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="dialog-close"
            // Never `<form method="dialog">`: closers sit inside the consumer's own
            // form in places like delete-user.tsx, and forms cannot nest.
            type="button"
            onClick={(event) => {
                onClick?.(event);
                setOpen(false);
            }}
            {...props}
        />
    );
}

type DialogContentProps = ComponentProps<'div'> & {
    /** Position of the panel — maps to daisyUI's `modal-*` placement classes. */
    position?: 'middle' | 'top' | 'bottom' | 'start' | 'end';
    dialogClassName?: string;
};

function DialogContent({
    className,
    children,
    position = 'middle',
    dialogClassName,
    ...props
}: DialogContentProps) {
    const { open, setOpen, dialogRef, titleId, descriptionId } = useDialog();

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        // Guarded so React's double-invoked effects cannot call showModal() twice,
        // which throws InvalidStateError.
        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open, dialogRef]);

    return (
        <dialog
            ref={dialogRef}
            data-slot="dialog"
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
            className={cn('modal', `modal-${position}`, dialogClassName)}
            // Escape reaches a `showModal()` dialog as `cancel`; refusing it is
            // what stops the key from closing the panel. `close` still runs when
            // something calls `dialog.close()` — the state sync below.
            onCancel={(event) => event.preventDefault()}
            onClose={() => setOpen(false)}
        >
            <div
                data-slot="dialog-content"
                // `relative` is load-bearing: daisyUI's `.modal-box` is static, so
                // without it the absolutely-positioned close button anchors to
                // `.modal` — which is `position: fixed; inset: 0` — and lands in the
                // viewport corner instead of the panel's.
                className={cn('relative modal-box grid gap-4', className)}
                {...props}
            >
                {children}

                {/* The only way out — see the docblock. Never conditional. */}
                <DialogClose
                    aria-label="Close"
                    className="btn absolute top-4 right-4 btn-circle btn-ghost btn-sm"
                >
                    <XIcon className="size-4" />
                </DialogClose>
            </div>
        </dialog>
    );
}

function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-header"
            className={cn(
                'flex flex-col gap-2 text-center sm:text-left',
                className,
            )}
            {...props}
        />
    );
}

function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn(
                'modal-action flex-col-reverse sm:flex-row',
                className,
            )}
            {...props}
        />
    );
}

function DialogTitle({ className, ...props }: ComponentProps<'h2'>) {
    const { titleId } = useDialog();

    return (
        <h2
            id={titleId}
            data-slot="dialog-title"
            className={cn('text-lg leading-none font-semibold', className)}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }: ComponentProps<'p'>) {
    const { descriptionId } = useDialog();

    return (
        <p
            id={descriptionId}
            data-slot="dialog-description"
            className={cn('text-sm text-base-content/60', className)}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
};
