import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, FileUp } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import PurchaseOrderImportDialog from '@/components/merchandising/purchase-order-import-dialog';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index, show } from '@/routes/merchandising/purchase-orders';
import type {
    ConflictDecisionOption,
    Filterable,
    ImportableBuyer,
    Paginated,
    PendingImport,
    PurchaseOrderFilters,
    PurchaseOrderListItem,
    PurchaseOrderView,
    StatusOption,
} from '@/types';

type Props = {
    purchaseOrders: Paginated<PurchaseOrderListItem>;
    /** Empty for anyone who cannot import — the query is not even run. */
    importBuyers: ImportableBuyer[];
    pendingImport: PendingImport | null;
    acceptedExtensions: string[];
    maxFileSizeKb: number;
    conflictDecisions: ConflictDecisionOption[];
    parseStatuses: StatusOption[];
    views: PurchaseOrderView[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: PurchaseOrderFilters;
};

/** The tabs, and what each one is for. */
const VIEW_LABELS: Record<PurchaseOrderView, string> = {
    current: 'Current',
    revisions: 'All revisions',
    failed: 'Failed imports',
};

/**
 * The imported purchase orders.
 *
 * The shared list apparatus (ARCHITECTURE.md §8.6), with one addition: the
 * **view tabs**, which choose the record set rather than narrowing it, so they
 * live in the toolbar beside the page size and not in the filter row. The users
 * list's Active/Historical tabs are the same pattern.
 *
 * The default view deliberately hides two things: superseded revisions, and
 * orders whose parse failed. Both are stored — a failed order keeps its warnings
 * next to the document it came from — but neither is an order in force, and a
 * list that mixed them would need a caveat on every row.
 *
 * There is no create button. An order arrives by importing the buyer's document,
 * through the dialog this page hosts — the same "one page with modals" shape as
 * every Admin and Settings surface.
 */
export default function PurchaseOrdersIndex({
    purchaseOrders,
    importBuyers,
    pendingImport,
    acceptedExtensions,
    maxFileSizeKb,
    conflictDecisions,
    parseStatuses,
    views,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canImport = useCan('merchandising.purchase-orders.import');

    const [importOpen, setImportOpen] = useState(false);

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['purchaseOrders', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Purchase orders"
                        description="Orders imported from a buyer's own document. Merchandising owns these up to the point production begins."
                    />

                    {canImport && (
                        <Button
                            onClick={() => setImportOpen(true)}
                            data-test="import-purchase-orders"
                        >
                            <FileUp /> Import
                        </Button>
                    )}
                </div>

                {canImport && (
                    <PurchaseOrderImportDialog
                        buyers={importBuyers}
                        acceptedExtensions={acceptedExtensions}
                        maxFileSizeKb={maxFileSizeKb}
                        decisions={conflictDecisions}
                        pendingImport={pendingImport}
                        open={importOpen}
                        onOpenChange={setImportOpen}
                    />
                )}

                {/* An import left unanswered survives on the server, so it is
                    offered back rather than thrown at the reader as a modal
                    they did not open. */}
                {canImport && pendingImport && !importOpen && (
                    <div
                        role="status"
                        className="alert alert-soft alert-warning"
                        data-test="pending-import"
                    >
                        <AlertTriangle className="size-5" />
                        <span>
                            {pendingImport.source_file_name} has{' '}
                            {pendingImport.conflicts.length} purchase order
                            {pendingImport.conflicts.length === 1
                                ? ''
                                : 's'}{' '}
                            already on file, waiting on your decision.
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setImportOpen(true)}
                            data-test="resume-import"
                        >
                            Resolve
                        </Button>
                    </div>
                )}

                <ListToolbar
                    perPage={filters.per_page}
                    perPageOptions={perPageOptions}
                    onPerPage={(per_page) => visit({ per_page })}
                    onClear={clear}
                    hasActiveFilter={hasActiveFilter}
                >
                    {/* Which record set, not which column value. */}
                    <div role="tablist" className="tabs tabs-box">
                        {views.map((view) => (
                            <button
                                key={view}
                                type="button"
                                role="tab"
                                className={`tab ${filters.view === view ? 'tab-active' : ''}`}
                                onClick={() => visit({ view })}
                                data-test={`view-${view}`}
                            >
                                {VIEW_LABELS[view]}
                            </button>
                        ))}
                    </div>
                </ListToolbar>

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <SortableHeader {...sortProps('po_number')}>
                                    PO number
                                </SortableHeader>
                                <SortableHeader {...sortProps('revision_no')}>
                                    Rev
                                </SortableHeader>
                                <th>Vendor</th>
                                <th>Factory</th>
                                <SortableHeader
                                    {...sortProps('vendor_ship_date')}
                                >
                                    Ship date
                                </SortableHeader>
                                <SortableHeader {...sortProps('total_qty')}>
                                    Quantity
                                </SortableHeader>
                                <SortableHeader {...sortProps('parse_status')}>
                                    Parse
                                </SortableHeader>
                                <th className="w-24" />
                            </tr>

                            <ColumnFilterRow
                                filterable={filterable}
                                draft={draft}
                                onFilter={setFilter}
                                cells={[
                                    {
                                        type: 'text',
                                        column: 'po_number',
                                        label: 'PO number',
                                    },
                                    /* A revision number is an ordinal within one
                                       order, not something anyone searches
                                       across the list. */
                                    { type: 'none' },
                                    {
                                        type: 'text',
                                        column: 'vendor_name',
                                        label: 'vendor',
                                    },
                                    {
                                        type: 'text',
                                        column: 'factory_name',
                                        label: 'factory',
                                    },
                                    /* Dates need a range, which the filter row
                                       does not do. Sort by it instead. */
                                    { type: 'none' },
                                    { type: 'none' },
                                    {
                                        type: 'select',
                                        column: 'parse_status',
                                        label: 'parse status',
                                        testId: 'parse-status-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...parseStatuses,
                                        ],
                                    },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>

                        <tbody>
                            {purchaseOrders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="text-center text-base-content/60"
                                    >
                                        No purchase orders match these filters.
                                    </td>
                                </tr>
                            )}

                            {purchaseOrders.data.map((order) => (
                                <tr key={order.id}>
                                    <td className="font-mono font-medium">
                                        {order.po_number}
                                    </td>

                                    <td className="tabular-nums">
                                        {order.revision_no}
                                        {!order.is_current && (
                                            <span className="ml-2 badge badge-ghost badge-xs">
                                                superseded
                                            </span>
                                        )}
                                    </td>

                                    <td className="max-w-48 truncate">
                                        {order.vendor_name ?? '—'}
                                    </td>

                                    <td className="max-w-48 truncate">
                                        {order.factory_name ?? '—'}
                                    </td>

                                    <td className="tabular-nums">
                                        {order.vendor_ship_date ?? '—'}
                                    </td>

                                    <td className="tabular-nums">
                                        {order.total_qty?.toLocaleString() ??
                                            '—'}
                                    </td>

                                    <td>
                                        <ParseStatusBadge
                                            status={order.parse_status}
                                            confidence={order.confidence}
                                        />
                                    </td>

                                    <td>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={show(order.id)}
                                                    data-test="open-purchase-order"
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={purchaseOrders} />
            </div>
        </>
    );
}

/**
 * How much the parser trusted this order.
 *
 * The colours carry the same meaning as the toast severities in §8.8: success is
 * done, a warning is something a person can resolve, an error is data that must
 * not be relied on.
 */
function ParseStatusBadge({
    status,
    confidence,
}: {
    status: PurchaseOrderListItem['parse_status'];
    confidence: number;
}) {
    const style = {
        success: 'badge-success',
        needs_review: 'badge-warning',
        failed: 'badge-error',
    }[status];

    const label = {
        success: 'Clean',
        needs_review: 'Needs review',
        failed: 'Failed',
    }[status];

    return (
        <span
            className={`badge badge-sm ${style}`}
            title={`Confidence ${(confidence * 100).toFixed(1)}%`}
        >
            {label}
        </span>
    );
}

PurchaseOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
