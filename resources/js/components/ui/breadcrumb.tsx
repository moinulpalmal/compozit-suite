import { Slot } from '@radix-ui/react-slot';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * daisyUI's `breadcrumbs` draws the separator itself with `li + li::before`, so there
 * is no `BreadcrumbSeparator` to render — adding one would double every separator.
 */
function Breadcrumb({ className, ...props }: ComponentProps<'nav'>) {
    return (
        <nav
            aria-label="breadcrumb"
            data-slot="breadcrumb"
            className={cn('breadcrumbs py-0 text-sm', className)}
            {...props}
        />
    );
}

function BreadcrumbList({ className, ...props }: ComponentProps<'ol'>) {
    return (
        <ol
            data-slot="breadcrumb-list"
            className={cn('text-base-content/60', className)}
            {...props}
        />
    );
}

function BreadcrumbItem({ className, ...props }: ComponentProps<'li'>) {
    return (
        <li
            data-slot="breadcrumb-item"
            className={cn('inline-flex items-center gap-1.5', className)}
            {...props}
        />
    );
}

function BreadcrumbLink({
    asChild,
    className,
    ...props
}: ComponentProps<'a'> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'a';

    return (
        <Comp
            data-slot="breadcrumb-link"
            className={cn(
                'transition-colors hover:text-base-content',
                className,
            )}
            {...props}
        />
    );
}

function BreadcrumbPage({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span
            data-slot="breadcrumb-page"
            role="link"
            aria-disabled="true"
            aria-current="page"
            className={cn('font-normal text-base-content', className)}
            {...props}
        />
    );
}

export {
    Breadcrumb,
    BreadcrumbList,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbPage,
};
