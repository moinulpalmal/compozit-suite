import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

/**
 * The guest auth shell: a branded card centred on a themed gradient.
 *
 * The background is built from daisyUI's base tokens rather than fixed colours so
 * it resolves correctly under every one of the 35 themes, dark ones included.
 * The card carries its own `bg-base-100` surface, which keeps the form readable
 * without needing a scrim over the gradient.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-linear-to-br from-base-100 via-base-200 to-base-300 p-6 md:p-10">
            <div
                aria-hidden
                className="pointer-events-none absolute -top-1/3 left-1/2 h-[50rem] w-[50rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
            />

            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-lg border border-base-300 bg-base-100 p-8 shadow-xl">
                    <div className="flex flex-col items-center gap-4">
                        <AppLogoIcon className="h-20 w-auto" />

                        <div className="space-y-1 text-center">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {name}
                            </h1>
                            {title && (
                                <p className="text-sm font-medium text-base-content/80">
                                    {title}
                                </p>
                            )}
                            {description && (
                                <p className="text-sm text-base-content/60">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
