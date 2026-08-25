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
 * focus trap, `inert` on the rest of the page, Escape to dismiss, and returning
 * focus to whatever opened it. daisyUI adds the surface and the scroll lock
 * (`--page-scroll-lock`), and paints its own backdrop, hiding `::backdrop`.
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
    showCloseButton?: boolean;
    closeOnBackdropClick?: boolean;
    dialogClassName?: string;
};

function DialogContent({
    className,
    children,
    position = 'middle',
    showCloseButton = true,
    closeOnBackdropClick = true,
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
            onClose={() => setOpen(false)}
        >
            <div
                data-slot="dialog-content"
                className={cn('modal-box grid gap-4', className)}
                {...props}
            >
                {children}

                {showCloseButton && (
                    <DialogClose
                        aria-label="Close"
                        className="btn absolute top-4 right-4 btn-circle btn-ghost btn-sm"
                    >
                        <XIcon className="size-4" />
                    </DialogClose>
                )}
            </div>

            {closeOnBackdropClick && (
                // daisyUI's backdrop idiom. A sibling of `.modal-box`, so it never
                // nests inside a consumer's form.
                <form method="dialog" className="modal-backdrop">
                    <button type="submit">Close</button>
                </form>
            )}
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
