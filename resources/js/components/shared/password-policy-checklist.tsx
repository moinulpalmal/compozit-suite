import { Check, ShieldCheck, X } from 'lucide-react';
import { usePasswordPolicy } from '@/hooks/use-password-policy';
import { cn } from '@/lib/utils';

/**
 * The password requirements, stated before the user invents a password and
 * ticked live as they type.
 *
 * Every requirement is read from the `passwordPolicy` shared prop, which the
 * server assembles from `config/auth.php` — the same config
 * `Password::defaults()` is built from. **No rule is restated here.** The
 * component this replaced hardcoded its own copy and went on advertising a
 * 12-character minimum after the policy had been lowered to 8, which is the
 * failure mode this indirection exists to prevent.
 *
 * `uncompromised` is not a tickable row: it is a Have I Been Pwned lookup that
 * only the server can perform, so rendering it as an unticked box would show a
 * requirement that never turns green no matter what the user types. It appears
 * as a note instead.
 *
 * Rendered under the **new password** field only — never under a confirmation
 * or current-password field, where it would state requirements that do not
 * apply to what is being typed.
 */
export default function PasswordPolicyChecklist({
    password,
    id,
    className,
}: {
    /** The live value of the password field this describes. */
    password: string;
    /** Point the field's `aria-describedby` here. */
    id?: string;
    className?: string;
}) {
    const policy = usePasswordPolicy();

    const requirements: { label: string; met: boolean }[] = [
        {
            label: `At least ${policy.minLength} characters`,
            met: password.length >= policy.minLength,
        },
    ];

    if (policy.mixedCase) {
        requirements.push({
            label: 'Upper and lower case letters',
            met: /[a-z]/.test(password) && /[A-Z]/.test(password),
        });
    } else if (policy.letters) {
        requirements.push({
            label: 'At least one letter',
            met: /[a-zA-Z]/.test(password),
        });
    }

    if (policy.numbers) {
        requirements.push({
            label: 'At least one number',
            met: /[0-9]/.test(password),
        });
    }

    if (policy.symbols) {
        requirements.push({
            label: 'At least one symbol',
            met: /[^a-zA-Z0-9]/.test(password),
        });
    }

    return (
        <div
            id={id}
            aria-live="polite"
            className={cn('grid gap-1 text-xs', className)}
            data-test="password-policy"
        >
            <ul className="grid gap-1">
                {requirements.map((requirement) => (
                    <li
                        key={requirement.label}
                        className={cn(
                            'flex items-center gap-1.5',
                            requirement.met
                                ? 'text-success'
                                : 'text-base-content/60',
                        )}
                    >
                        {requirement.met ? (
                            <Check className="size-3.5 shrink-0" />
                        ) : (
                            <X className="size-3.5 shrink-0" />
                        )}

                        <span>{requirement.label}</span>

                        <span className="sr-only">
                            {requirement.met ? '— met' : '— not met yet'}
                        </span>
                    </li>
                ))}
            </ul>

            {policy.uncompromised && (
                <p className="flex items-center gap-1.5 text-base-content/60">
                    <ShieldCheck className="size-3.5 shrink-0" />
                    <span>
                        Checked against known breached passwords when you save.
                    </span>
                </p>
            )}
        </div>
    );
}
