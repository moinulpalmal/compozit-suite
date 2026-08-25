import { Toaster as Sonner } from 'sonner';
import type { ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

function Toaster({ ...props }: ToasterProps) {
    const { isDark } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={isDark ? 'dark' : 'light'}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--color-base-100)',
                    '--normal-text': 'var(--color-base-content)',
                    '--normal-border': 'var(--color-base-300)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
