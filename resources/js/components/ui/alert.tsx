import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * daisyUI's `alert` lays its children out with `grid-auto-flow: column`, which would
 * put the icon, title and description in three columns. The explicit grid below keeps
 * shadcn's icon-plus-stacked-content shape while daisyUI supplies the surface colours.
 */
const alertVariants = cva(
    'alert grid w-full grid-flow-row grid-cols-[0_1fr] items-start gap-x-3 gap-y-0.5 px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current',
    {
        variants: {
            variant: {
                default: 'bg-base-100 text-base-content',
                destructive: 'alert-soft alert-error',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Alert({
    className,
    variant,
    ...props
}: ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-title"
            className={cn(
                'col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight',
                className,
            )}
            {...props}
        />
    );
}

function AlertDescription({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="alert-description"
            className={cn(
                'col-start-2 grid justify-items-start gap-1 text-sm opacity-80 [&_p]:leading-relaxed',
                className,
            )}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
