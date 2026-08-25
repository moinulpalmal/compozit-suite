import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Spinner({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span
            data-slot="spinner"
            role="status"
            aria-label="Loading"
            className={cn('loading loading-sm loading-spinner', className)}
            {...props}
        />
    );
}

export { Spinner };
