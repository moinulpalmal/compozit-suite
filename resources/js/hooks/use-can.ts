import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

type SharedProps = {
    auth?: Auth;
};

/**
 * The signed-in user's effective permission names, shared by
 * `HandleInertiaRequests`. A super admin is represented by `['*']`.
 */
export function usePermissions(): string[] {
    return usePage<SharedProps>().props.auth?.permissions ?? [];
}

/**
 * Whether the signed-in user holds a permission.
 *
 * This decides what the UI *shows*. It is not authorization — the route
 * middleware and the module's policy are.
 */
export function useCan(permission: string): boolean {
    const permissions = usePermissions();

    return permissions.includes('*') || permissions.includes(permission);
}
