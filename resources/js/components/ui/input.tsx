import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: ComponentProps<'input'>) {
    // daisyUI's `input` is not full width on its own — every form here expects it to be.
    const isInvalid =
        props['aria-invalid'] === true || props['aria-invalid'] === 'true';

    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'input w-full',
                isInvalid && 'input-error',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
