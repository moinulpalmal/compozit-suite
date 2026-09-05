import { usePage } from '@inertiajs/react';
import type { PasswordPolicy } from '@/types';

type SharedProps = {
    passwordPolicy?: PasswordPolicy;
};

/**
 * The server's password policy, shared by `HandleInertiaRequests` on every
 * page — including guest pages such as password reset.
 *
 * The fallback exists only so a page cannot crash if the prop is ever missing
 * (an old cached Inertia response, a test rendering a page in isolation). It is
 * deliberately the loosest possible reading: showing *fewer* requirements than
 * the server enforces produces a confusing rejection, whereas showing more
 * would be a lie. Never treat it as the policy.
 */
export function usePasswordPolicy(): PasswordPolicy {
    return (
        usePage<SharedProps>().props.passwordPolicy ?? {
            minLength: 8,
            mixedCase: false,
            letters: false,
            numbers: false,
            symbols: false,
            uncompromised: false,
            hint: '',
        }
    );
}
