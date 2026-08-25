import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type SeparatorProps = ComponentProps<'div'> & {
    orientation?: 'horizontal' | 'vertical';
    /** Purely visual separators are hidden from assistive technology. */
    decorative?: boolean;
};

function Separator({
    className,
    orientation = 'horizontal',
    decorative = true,
    ...props
}: SeparatorProps) {
    return (
        <div
            data-slot="separator-root"
            data-orientation={orientation}
            role={decorative ? 'none' : 'separator'}
            aria-orientation={decorative ? undefined : orientation}
            className={cn(
                'shrink-0 bg-base-300',
                orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px',
                className,
            )}
            {...props}
        />
    );
}

export { Separator };
