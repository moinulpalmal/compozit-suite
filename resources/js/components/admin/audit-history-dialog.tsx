import { useEffect, useState  } from 'react';
import type {ReactNode} from 'react';
import AuditDiffTable from '@/components/admin/audit-diff-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { history } from '@/routes/admin/audit-logs';
import type { AuditLogListItem } from '@/types';

type State =
    | { status: 'loading' }
    | { status: 'ready'; audits: AuditLogListItem[] }
    | { status: 'failed' };

/**
 * Everything that has ever happened to one record.
 *
 * The list answers "what changed recently"; this answers "what has this record
 * been through", which no amount of filtering the list gets to as directly.
 *
 * **This one does fetch**, because a record's whole history is unbounded and
 * cannot ride along with a list row. A plain `fetch` with an `AbortController`
 * rather than `useHttp`: it is a read with no form behind it (ARCHITECTURE.md
 * §8.4), and `hooks/use-availability.ts` is the reference for the shape.
 *
 * The request is made by `HistoryBody`, which `DialogContent` mounts only while
 * the panel is open (§8.7) — so nothing is fetched for the ninety-nine rows on
 * screen whose history nobody asked for, and reopening re-fetches rather than
 * showing what the record looked like some minutes ago.
 */
export default function AuditHistoryDialog({
    type,
    id,
    label,
    children,
}: {
    type: string;
    id: number;
    label: string;
    children: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-4xl">
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    Every recorded change to this record, newest first.
                </DialogDescription>

                <HistoryBody type={type} id={id} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary" type="button">
                            Close
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function HistoryBody({ type, id }: { type: string; id: number }) {
    const [state, setState] = useState<State>({ status: 'loading' });

    useEffect(() => {
        const controller = new AbortController();

        fetch(history.url({ query: { type, id } }), {
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                /*
                 * Without this, Laravel's StartSession records this URL as the
                 * session's "previous URL", and every later `back()` would
                 * redirect onto this JSON endpoint. Same reason
                 * `use-availability.ts` sends it.
                 */
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) =>
                response.ok ? response.json() : Promise.reject(response),
            )
            .then((body: { audits?: AuditLogListItem[] }) =>
                setState({ status: 'ready', audits: body.audits ?? [] }),
            )
            .catch((error: unknown) => {
                // An aborted request is the dialog closing, not a failure.
                if (!controller.signal.aborted) {
                    setState({ status: 'failed' });
                }

                void error;
            });

        return () => controller.abort();
    }, [type, id]);

    if (state.status === 'loading') {
        return (
            <div className="space-y-2 py-4">
                <div className="h-4 w-1/3 animate-pulse rounded bg-base-300" />
                <div className="h-4 w-2/3 animate-pulse rounded bg-base-300" />
                <div className="h-4 w-1/2 animate-pulse rounded bg-base-300" />
            </div>
        );
    }

    if (state.status === 'failed') {
        return (
            <p className="py-4 text-center text-sm text-error">
                This record's history could not be loaded. Try again.
            </p>
        );
    }

    if (state.audits.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-base-content/60">
                Nothing has been recorded against this record.
            </p>
        );
    }

    return (
        <div className="max-h-[60vh] space-y-4 overflow-y-auto">
            {state.audits.map((audit) => (
                <section key={audit.id} className="space-y-2">
                    <header className="flex flex-wrap items-baseline gap-2 text-sm">
                        <span className="badge badge-ghost badge-sm">
                            {audit.event_label}
                        </span>
                        <span className="font-medium">
                            {audit.actor_name ?? 'System'}
                        </span>
                        <span className="text-xs text-base-content/60">
                            {audit.created_at
                                ? new Date(audit.created_at).toLocaleString()
                                : ''}
                        </span>
                    </header>

                    <AuditDiffTable audit={audit} />
                </section>
            ))}
        </div>
    );
}
