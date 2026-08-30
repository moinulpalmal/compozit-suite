import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/**
 * One module's sidebar links. `label` is both the group heading and the key its
 * collapsed state is remembered under — see ARCHITECTURE.md §8.3.
 */
export type NavGroup = {
    label: string;
    items: NavItem[];
};
