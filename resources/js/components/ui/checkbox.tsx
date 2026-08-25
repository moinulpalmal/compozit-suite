import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A native checkbox: better assistive-technology support than Radix's
 * `button[role="checkbox"]` plus hidden input, and it submits with the form
 * exactly the same way.
 */
function Checkbox({ className, ...props }: ComponentProps<'input'>) {
    return (
        <input
            type="checkbox"
            data-slot="checkbox"
            className={cn(
                'peer checkbox checkbox-sm checkbox-primary',
                className,
            )}
            {...props}
        />
    );
}

export { Checkbox };
