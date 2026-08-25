import type { ComponentProps } from 'react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * An edge-anchored panel built on the same native `<dialog>` primitive as Dialog.
 *
 * daisyUI's `drawer` is deliberately not used here: it is a CSS checkbox with no
 * focus trap, no Escape handling and no `inert`, which would be a real regression
 * for a modal mobile navigation.
 */
const sideToPosition = {
    top: 'top',
    bottom: 'bottom',
    left: 'start',
    right: 'end',
} as const;

type SheetSide = keyof typeof sideToPosition;

const Sheet = Dialog;
const SheetTrigger = DialogTrigger;
const SheetClose = DialogClose;

function SheetContent({
    className,
    side = 'right',
    ...props
}: Omit<ComponentProps<typeof DialogContent>, 'position'> & {
    side?: SheetSide;
}) {
    const isHorizontal = side === 'left' || side === 'right';

    return (
        <DialogContent
            data-slot="sheet-content"
            position={sideToPosition[side]}
            className={cn(
                'flex flex-col gap-4 rounded-none border-base-300 p-4 shadow-lg',
                isHorizontal
                    ? 'w-3/4 sm:max-w-sm'
                    : 'max-h-3/4 w-full max-w-none',
                side === 'left' && 'border-r',
                side === 'right' && 'border-l',
                side === 'top' && 'border-b',
                side === 'bottom' && 'border-t',
                className,
            )}
            {...props}
        />
    );
}

function SheetHeader({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-header"
            className={cn('flex flex-col gap-1.5', className)}
            {...props}
        />
    );
}

function SheetFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="sheet-footer"
            className={cn('mt-auto flex flex-col gap-2', className)}
            {...props}
        />
    );
}

function SheetTitle({
    className,
    ...props
}: ComponentProps<typeof DialogTitle>) {
    return (
        <DialogTitle
            data-slot="sheet-title"
            className={cn(
                'text-base leading-normal font-semibold text-base-content',
                className,
            )}
            {...props}
        />
    );
}

function SheetDescription({
    className,
    ...props
}: ComponentProps<typeof DialogDescription>) {
    return (
        <DialogDescription
            data-slot="sheet-description"
            className={cn('text-sm text-base-content/60', className)}
            {...props}
        />
    );
}

export {
    Sheet,
    SheetTrigger,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetFooter,
    SheetTitle,
    SheetDescription,
};
