import { router } from '@inertiajs/react';
import { createElement, useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

const TOAST_TYPES: ReadonlyArray<FlashToast['type']> = [
    'success',
    'info',
    'warning',
    'error',
];

function isToastType(value: unknown): value is FlashToast['type'] {
    return TOAST_TYPES.includes(value as FlashToast['type']);
}

/**
 * Raise a toast for every `Inertia::flash('toast', …)` the server sends.
 *
 * The message is wrapped rather than passed as a bare string: sonner announces
 * through one `aria-live="polite"` container for every toast, and a failure the
 * user has to act on should interrupt. A nested `role="alert"` is the only lever
 * sonner exposes for that.
 *
 * An unrecognised `type` falls back to a plain toast. It used to index straight
 * into `toast[type]`, so one typo in a controller threw a TypeError and the user
 * saw nothing at all.
 */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const message = createElement(
                'span',
                { role: data.type === 'error' ? 'alert' : 'status' },
                data.message,
            );

            if (isToastType(data.type)) {
                toast[data.type](message);

                return;
            }

            toast(message);
        });
    }, []);
}
