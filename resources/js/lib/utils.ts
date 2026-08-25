import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';
import type { DaisyClassGroupId } from '@/lib/daisy-class-groups';
import { daisyClassGroups } from '@/lib/daisy-class-groups';

const twMerge = extendTailwindMerge<DaisyClassGroupId>({
    extend: {
        classGroups: daisyClassGroups,
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
