import { Head } from '@inertiajs/react';
import { Eye, History } from 'lucide-react';
import AuditDiffDialog from '@/components/admin/audit-diff-dialog';
import AuditHistoryDialog from '@/components/admin/audit-history-dialog';
import Heading from '@/components/heading';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/hooks/use-list-filters';
import { index } from '@/routes/admin/audit-logs';
import type {
    AuditLogFilters,
    AuditLogListItem,
    Filterable,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    auditLogs: Paginated<AuditLogListItem>;
    events: StatusOption[];
    modelTypes: StatusOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: AuditLogFilters;
};

/**
 * The audit trail — ARCHITECTURE.md §9.3.
 *
 * One page with modals, the Admin shape, but **read-only**: there is no create,
 * no edit and no delete, because a trail an administrator can change answers
 * nothing. The two dialogs are a diff of one event and the full history of one
 * record.
 *
 * Paginated, sortable and filtered per column like every list (§8.6), and
 * **newest first** — `AuditLogIndexRequest::filterValues()` inverts the shared
 * `asc` default for this surface, because a log is read from the top.
 *
 * Reaching this page at all needs `admin.audit-logs.view`, which the seeder gives
 * to `super-admin` alone, so there is no `useCan()` gating inside it: anyone
 * rendering it can see everything on it.
 */
export default function AuditLogsIndex({
    auditLogs,
    events,
    modelTypes,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['auditLogs', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Audit log" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Audit log"
                    description="Every recorded change, who made it and what moved. Records are written automatically and cannot be edited or removed."
                />

                <ListToolbar
                    perPage={filters.per_page}
                    perPageOptions={perPageOptions}
                    onPerPage={(per_page) => visit({ per_page })}
                    onClear={clear}
                    hasActiveFilter={hasActiveFilter}
                />

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <SortableHeader {...sortProps('created_at')}>
                                    When
                                </SortableHeader>
                                <SortableHeader {...sortProps('actor_name')}>
                                    Who
                                </SortableHeader>
                                <SortableHeader
                                    {...sortProps('auditable_type')}
                                >
                                    Record
                                </SortableHeader>
                                <SortableHeader {...sortProps('event')}>
                                    Event
                                </SortableHeader>
                                {/* Not filterable: `changed` is derived from the
                                    stored JSON, so narrowing by it would mean
                                    scanning every row's values. */}
                                <th>Changed</th>
                                <th>IP</th>
                                <th className="w-24" />
                            </tr>

                            <ColumnFilterRow
                                filterable={filterable}
                                draft={draft}
                                onFilter={setFilter}
                                cells={[
                                    { type: 'none' },
                                    {
                                        type: 'text',
                                        column: 'actor_name',
                                        label: 'who',
                                    },
                                    {
                                        type: 'select',
                                        column: 'auditable_type',
                                        label: 'record type',
                                        testId: 'model-type-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...modelTypes,
                                        ],
                                    },
                                    {
                                        type: 'select',
                                        column: 'event',
                                        label: 'event',
                                        testId: 'event-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...events,
                                        ],
                                    },
                                    { type: 'none' },
                                    {
                                        type: 'text',
                                        column: 'ip_address',
                                        label: 'IP address',
                                    },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>
                        <tbody>
                            {auditLogs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-center text-base-content/60"
                                    >
                                        No recorded changes match these filters.
                                    </td>
                                </tr>
                            )}

                            {auditLogs.data.map((audit) => (
                                <tr key={audit.id}>
                                    <td className="text-xs whitespace-nowrap">
                                        {audit.created_at
                                            ? new Date(
                                                  audit.created_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </td>

                                    <td>
                                        <div className="font-medium">
                                            {/* Stamped at write time, so a
                                                deleted account still reads as
                                                itself rather than "System". */}
                                            {audit.actor_name ?? (
                                                <span className="text-base-content/50">
                                                    System
                                                </span>
                                            )}
                                        </div>
                                        {audit.actor_employee_id && (
                                            <div className="font-mono text-xs text-base-content/60">
                                                {audit.actor_employee_id}
                                            </div>
                                        )}
                                    </td>

                                    <td className="text-xs">
                                        {audit.auditable_type === null ? (
                                            <span className="text-base-content/50">
                                                —
                                            </span>
                                        ) : (
                                            <>
                                                {audit.model_label}{' '}
                                                <span className="text-base-content/60">
                                                    #{audit.auditable_id}
                                                </span>
                                            </>
                                        )}
                                    </td>

                                    <td>
                                        <span className="badge badge-ghost badge-sm whitespace-nowrap">
                                            {audit.event_label}
                                        </span>
                                    </td>

                                    <td className="font-mono text-xs text-base-content/70">
                                        {audit.changed.length === 0 ? (
                                            <span className="text-base-content/50">
                                                —
                                            </span>
                                        ) : (
                                            audit.changed.join(', ')
                                        )}
                                    </td>

                                    <td className="font-mono text-xs text-base-content/70">
                                        {audit.ip_address ?? '—'}
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            <AuditDiffDialog audit={audit}>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`View this ${audit.event_label.toLowerCase()} event`}
                                                    data-test="view-audit"
                                                >
                                                    <Eye />
                                                </Button>
                                            </AuditDiffDialog>

                                            {/* An authentication event names no
                                                record, so there is no history to
                                                open for it. */}
                                            {audit.auditable_type !== null &&
                                                audit.auditable_id !== null && (
                                                    <AuditHistoryDialog
                                                        type={
                                                            audit.auditable_type
                                                        }
                                                        id={audit.auditable_id}
                                                        label={`${audit.model_label} #${audit.auditable_id}`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Full history of ${audit.model_label} #${audit.auditable_id}`}
                                                            data-test="view-audit-history"
                                                        >
                                                            <History />
                                                        </Button>
                                                    </AuditHistoryDialog>
                                                )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={auditLogs} />
            </div>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
