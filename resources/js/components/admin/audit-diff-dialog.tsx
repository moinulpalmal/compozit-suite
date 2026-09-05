import type { ReactNode } from 'react';
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
import type { AuditLogListItem } from '@/types';

/**
 * One recorded change in full: the diff, and where it came from.
 *
 * **No fetch.** The row already carries its old and new values — see
 * `AuditLogController::index()` for why that is affordable — so opening this
 * costs nothing and works offline of the server entirely.
 *
 * The footer carries the request context (URL, IP, browser) rather than the
 * table, because it answers a different question: the diff says what changed,
 * this says from where.
 */
export default function AuditDiffDialog({
    audit,
    children,
}: {
    audit: AuditLogListItem;
    children: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-3xl">
                <DialogTitle>
                    {audit.model_label
                        ? `${audit.model_label} #${audit.auditable_id}`
                        : audit.event_label}
                </DialogTitle>

                <DialogDescription>
                    {audit.event_label} by {audit.actor_name ?? 'System'}
                    {audit.created_at
                        ? ` on ${new Date(audit.created_at).toLocaleString()}`
                        : ''}
                    .
                </DialogDescription>

                <AuditDiffTable audit={audit} />

                <dl className="mt-4 grid gap-1 text-xs text-base-content/60">
                    <Context label="URL" value={audit.url} />
                    <Context label="IP address" value={audit.ip_address} />
                    <Context label="Browser" value={audit.user_agent} />
                    <Context label="Tags" value={audit.tags} />
                </dl>

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

/** One line of request context, omitted entirely when it was not recorded. */
function Context({ label, value }: { label: string; value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex gap-2">
            <dt className="shrink-0 font-medium">{label}:</dt>
            <dd className="break-all">{value}</dd>
        </div>
    );
}
