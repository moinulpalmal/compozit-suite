import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * daisyUI's `btn` already supplies layout, radius, font weight, transitions, the
 * focus ring and the disabled state, all from the active theme. Only the icon
 * sizing rule is kept from shadcn so existing `<Icon className="h-5 w-5" />`
 * children keep overriding it.
 *
 * Conflicting daisyUI modifiers passed through `className` win over the variant
 * (see `lib/daisy-class-groups.ts`), so `size="icon" className="btn-circle"` works.
 */
const buttonVariants = cva(
    "btn [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'btn-primary',
                destructive: 'btn-error',
                outline: 'btn-outline',
                secondary: 'btn-secondary',
                ghost: 'btn-ghost',
                link: 'btn-link',
            },
            size: {
                default: 'btn-md',
                sm: 'btn-sm',
                lg: 'btn-lg',
                icon: 'btn-square btn-md',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
