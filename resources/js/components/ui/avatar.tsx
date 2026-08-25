import type { ComponentProps } from 'react';
import { createContext, use, useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * daisyUI's `avatar` is presentation only — it has no notion of an image that is
 * still loading or has failed. That load state was the one thing Radix's Avatar
 * genuinely provided, so it is reimplemented here: the fallback shows until the
 * image reports `load`, and stays for good if it reports `error`.
 */
type AvatarStatus = 'idle' | 'loaded' | 'error';

type AvatarContextValue = {
    status: AvatarStatus;
    setStatus: (status: AvatarStatus) => void;
};

const AvatarContext = createContext<AvatarContextValue | null>(null);

function useAvatar(): AvatarContextValue {
    const context = use(AvatarContext);

    if (!context) {
        throw new Error('Avatar parts must be rendered inside <Avatar>.');
    }

    return context;
}

function Avatar({ className, ...props }: ComponentProps<'span'>) {
    const [status, setStatus] = useState<AvatarStatus>('idle');

    return (
        <AvatarContext value={{ status, setStatus }}>
            <span
                data-slot="avatar"
                className={cn(
                    'avatar relative flex size-8 shrink-0 overflow-hidden rounded-full',
                    className,
                )}
                {...props}
            />
        </AvatarContext>
    );
}

function AvatarImage({
    className,
    src,
    onLoad,
    onError,
    ...props
}: ComponentProps<'img'>) {
    const { status, setStatus } = useAvatar();

    useEffect(() => {
        setStatus('idle');
    }, [src, setStatus]);

    if (!src) {
        return null;
    }

    return (
        <img
            data-slot="avatar-image"
            src={src}
            // Kept mounted while hidden so the browser still reports load/error.
            className={cn(
                'aspect-square size-full',
                status !== 'loaded' && 'hidden',
                className,
            )}
            onLoad={(event) => {
                setStatus('loaded');
                onLoad?.(event);
            }}
            onError={(event) => {
                setStatus('error');
                onError?.(event);
            }}
            {...props}
        />
    );
}

function AvatarFallback({ className, ...props }: ComponentProps<'span'>) {
    const { status } = useAvatar();

    if (status === 'loaded') {
        return null;
    }

    return (
        <span
            data-slot="avatar-fallback"
            className={cn(
                'flex size-full items-center justify-center rounded-full bg-base-300 text-xs font-medium text-base-content',
                className,
            )}
            {...props}
        />
    );
}

export { Avatar, AvatarImage, AvatarFallback };
