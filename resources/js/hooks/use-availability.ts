import { useEffect, useState } from 'react';
import { availability } from '@/routes/admin/users';

export type AvailabilityState = 'idle' | 'checking' | 'available' | 'taken';

/** Below this, the value is too short to be worth asking the server about. */
const MINIMUM_LENGTH = 3;

const DEBOUNCE_MS = 400;

/**
 * Ask the server whether an employee ID or email is still free, as it is typed.
 *
 * Soft-deleted users count as taken — the unique indexes do not exclude them —
 * so this surfaces the collision before the form is submitted rather than after.
 * It is a convenience: `UserStoreRequest` and `UserUpdateRequest` are what
 * actually enforce uniqueness.
 *
 * A plain `fetch` rather than `useHttp`: this is a read, not a form submission,
 * so there is no form state to own (see ARCHITECTURE.md §8.4).
 *
 * @param ignore The id of the user being edited, so their own value reads free.
 */
export function useAvailability(
    field: 'employee_id' | 'email',
    value: string,
    ignore?: number,
): AvailabilityState {
    const trimmed = value.trim();
    const tooShort = trimmed.length < MINIMUM_LENGTH;

    // Keyed by the value it describes, so a result for a stale keystroke is
    // simply not matched below rather than having to be cancelled.
    const [result, setResult] = useState<{
        value: string;
        available: boolean;
    } | null>(null);

    useEffect(() => {
        if (tooShort) {
            return;
        }

        const controller = new AbortController();

        const timer = setTimeout(() => {
            fetch(
                availability.url({
                    query: { field, value: trimmed, ignore },
                }),
                {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        // Without this, Laravel's StartSession records this URL
                        // as the session's "previous URL" — and every later
                        // `back()` (a validation failure, a guard refusal)
                        // would redirect the user onto this JSON endpoint.
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            )
                .then((response) =>
                    response.ok ? response.json() : Promise.reject(response),
                )
                .then((body: { available?: boolean }) =>
                    setResult({
                        value: trimmed,
                        available: body.available === true,
                    }),
                )
                // A failed check must not block the form; the server decides.
                .catch(() => undefined);
        }, DEBOUNCE_MS);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [field, trimmed, ignore, tooShort]);

    if (tooShort) {
        return 'idle';
    }

    if (result?.value === trimmed) {
        return result.available ? 'available' : 'taken';
    }

    return 'checking';
}
