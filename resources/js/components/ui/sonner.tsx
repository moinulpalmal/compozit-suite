import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import { Toaster as Sonner } from 'sonner';
import type { ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { FlashToast } from '@/types/ui';

/**
 * The application's only toast surface, fed by `Inertia::flash('toast', …)`.
 *
 * Two things it does differently from stock sonner:
 *
 * 1. **Severity is a colour, and the colour is daisyUI's.** `richColors` turns on
 *    sonner's per-type styling, and {@link soft} then overrides its palette with
 *    the same `color-mix` formula daisyUI's `alert-soft` uses, so a success toast
 *    and an inline `<Alert>` are the same green and both follow the theme into
 *    dark mode. Enabling `richColors` without that override would ship sonner's
 *    own green next to daisyUI's.
 * 2. **Nothing auto-dismisses.** `duration: Infinity` plus `closeButton`: a toast
 *    stays until it is closed, the same rule modals follow in `dialog.tsx`. The
 *    cost is deliberate — a burst of saves stacks up, and sonner drops the oldest
 *    past `visibleToasts`.
 */

/** daisyUI colour tokens, which happen to share sonner's type names exactly. */
const TOAST_TYPES = ['success', 'info', 'warning', 'error'] as const;

/**
 * daisyUI's `alert-soft` treatment, expressed as the three CSS variables sonner
 * reads for one toast type.
 */
function soft(type: FlashToast['type']): Record<string, string> {
    return {
        [`--${type}-bg`]: `color-mix(in oklab, var(--color-${type}) 8%, var(--color-base-100))`,
        [`--${type}-text`]: `var(--color-${type})`,
        [`--${type}-border`]: `color-mix(in oklab, var(--color-${type}) 10%, var(--color-base-100))`,
    };
}

function Toaster({ ...props }: ToasterProps) {
    const { isDark } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={isDark ? 'dark' : 'light'}
            className="toaster group"
            position="bottom-right"
            richColors
            closeButton
            duration={Infinity}
            // lucide, so toasts share the icon vocabulary of every other surface
            // rather than bringing sonner's second one.
            icons={{
                success: <CircleCheck className="size-4" />,
                info: <Info className="size-4" />,
                warning: <TriangleAlert className="size-4" />,
                error: <CircleAlert className="size-4" />,
            }}
            style={
                {
                    '--normal-bg': 'var(--color-base-100)',
                    '--normal-text': 'var(--color-base-content)',
                    '--normal-border': 'var(--color-base-300)',
                    ...TOAST_TYPES.reduce(
                        (tokens, type) => ({ ...tokens, ...soft(type) }),
                        {},
                    ),
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
