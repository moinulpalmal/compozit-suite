import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * The message under a rejected field.
 *
 * **`role="alert"` is not decoration.** A form modal moves focus to the first
 * rejected field on a failed save (ARCHITECTURE.md §8.10), and this is what tells
 * a screen-reader user *why* focus moved. It matches the treatment §8.8 gives the
 * `error` toast, which interrupts for the same reason. The element only exists
 * while there is a message, so the role fires on insertion — which is exactly when
 * there is something to announce.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            role="alert"
            {...props}
            className={cn('text-sm text-red-600 dark:text-red-400', className)}
        >
            {message}
        </p>
    ) : null;
}
