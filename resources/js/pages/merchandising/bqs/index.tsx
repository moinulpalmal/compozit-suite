import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, FileUp } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    buildReturnQuery,
    RETURN_PARAM,
} from '@/components/merchandising/back-link';
import BqsImportDialog from '@/components/merchandising/bqs-import-dialog';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index, show } from '@/routes/merchandising/bqs';
import type {
    BqsConflictDecisionOption,
    BqsFilters,
    BqsListItem,
    BqsParseStatus,
    BqsPendingImport,
    BqsView,
    Filterable,
    ImportableBuyer,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    sheets: Paginated<BqsListItem>;
    /** Empty for anyone who cannot import — the query is not even run. */
    importBuyers: ImportableBuyer[];
    pendingImport: BqsPendingImport | null;
    maxFileSizeKb: number;
    conflictDecisions: BqsConflictDecisionOption[];
    parseStatuses: StatusOption[];
    views: BqsView[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: BqsFilters;
};

/** The tabs, and what each one is for. */
const VIEW_LABELS: Record<BqsView, string> = {
    current: 'Current',
    revisions: 'All revisions',
};

/**
 * The imported BQS records.
 *
 * The shared list apparatus (ARCHITECTURE.md §8.6), with the same **view tabs**
 * addition the purchase-order list has: they choose the record set rather than
 * narrowing it, so they live in the toolbar and not in the filter row.
 *
 * There is no "failed" tab here, unlike purchase orders. A workbook that cannot be
 * read is refused before a BQS exists, so there is no failed record to list — the
 * diagnosis lives on the import, which the detail page shows.
 *
 * There is no create button. A BQS arrives by importing the buyer's workbook, through
 * the dialog this page hosts — the same "one page with modals" shape as every other
 * surface.
 */
export default function BqsIndex({
    sheets,
    importBuyers,
    pendingImport,
    maxFileSizeKb,
    conflictDecisions,
    parseStatuses,
    views,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canImport = useCan('merchandising.bqs.import');

    const [importOpen, setImportOpen] = useState(false);

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['sheets', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    /*
     * Handed to every row's link so the detail page can come back to *this* list —
     * these filters, this sort, this page — rather than to the top of an unfiltered
     * one. See `BackLink`.
     */
    const back = buildReturnQuery(filters, sheets.current_page);

    return (
        <>
            <Head title="BQS" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="BQS"
                        description="The buyer's buy plan for a product program — every colourway, its quantities, its cost stack and its monthly DC intake."
                    />

                    {canImport && (
                        <Button
                            onClick={() => setImportOpen(true)}
                            data-test="import-bqs"
                        >
                            <FileUp /> Import
                        </Button>
                    )}
                </div>

                {canImport && (
                    <BqsImportDialog
                        buyers={importBuyers}
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
                        data-test="pending-bqs-import"
                    >
                        <AlertTriangle className="size-5" />
                        <span>
                            {pendingImport.source_file_name} overlaps{' '}
                            {pendingImport.collides_with_title}, waiting on your
                            decision.
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setImportOpen(true)}
                            data-test="resume-bqs-import"
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
                                <SortableHeader {...sortProps('title')}>
                                    BQS
                                </SortableHeader>
                                <SortableHeader {...sortProps('bqs_date')}>
                                    BQS date
                                </SortableHeader>
                                <SortableHeader {...sortProps('revision_no')}>
                                    Rev
                                </SortableHeader>
                                <SortableHeader {...sortProps('fye')}>
                                    FYE
                                </SortableHeader>
                                <SortableHeader {...sortProps('season')}>
                                    Season
                                </SortableHeader>
                                <SortableHeader {...sortProps('department')}>
                                    Department
                                </SortableHeader>
                                <SortableHeader {...sortProps('row_count')}>
                                    Rows
                                </SortableHeader>
                                <SortableHeader {...sortProps('parse_status')}>
                                    Status
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
                                        column: 'title',
                                        label: 'BQS',
                                    },
                                    /* A prefix, so 2026 and 2026-09 both narrow.
                                       A range needs controls the filter row
                                       does not have. */
                                    {
                                        type: 'text',
                                        column: 'bqs_date',
                                        label: 'date',
                                    },
                                    /* A revision number is an ordinal within one
                                       BQS, not something anyone searches
                                       across the list. */
                                    { type: 'none' },
                                    {
                                        type: 'text',
                                        column: 'fye',
                                        label: 'FYE',
                                    },
                                    {
                                        type: 'text',
                                        column: 'season',
                                        label: 'season',
                                    },
                                    {
                                        type: 'text',
                                        column: 'department',
                                        label: 'department',
                                    },
                                    { type: 'none' },
                                    {
                                        type: 'select',
                                        column: 'parse_status',
                                        label: 'status',
                                        testId: 'bqs-parse-status-filter',
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
                            {sheets.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="text-center text-base-content/60"
                                    >
                                        No BQS records match these filters.
                                    </td>
                                </tr>
                            )}

                            {sheets.data.map((sheet) => (
                                <tr key={sheet.id}>
                                    <td className="max-w-64 truncate font-medium">
                                        {sheet.title}
                                    </td>

                                    <td className="tabular-nums">
                                        {sheet.bqs_date}
                                    </td>

                                    <td className="tabular-nums">
                                        {sheet.revision_no}
                                        {!sheet.is_current && (
                                            <span className="ml-2 badge badge-ghost badge-xs">
                                                superseded
                                            </span>
                                        )}
                                    </td>

                                    <td>{sheet.fye ?? '—'}</td>
                                    <td>{sheet.season ?? '—'}</td>

                                    <td className="max-w-40 truncate">
                                        {sheet.department ?? '—'}
                                    </td>

                                    <td className="tabular-nums">
                                        {sheet.row_count.toLocaleString()}
                                    </td>

                                    <td>
                                        <ParseStatusBadge
                                            status={sheet.parse_status}
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
                                                    href={show(sheet.id, {
                                                        query: {
                                                            [RETURN_PARAM]:
                                                                back,
                                                        },
                                                    })}
                                                    data-test="open-bqs"
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

                <Pagination page={sheets} />
            </div>
        </>
    );
}

/**
 * How cleanly the workbook read.
 *
 * The colours carry the same meaning as the toast severities in §8.8. Note there is
 * no confidence figure to show, unlike a purchase order: a workbook is structured, so
 * a column either mapped or it did not.
 */
function ParseStatusBadge({ status }: { status: BqsParseStatus }) {
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

    return <span className={`badge badge-sm ${style}`}>{label}</span>;
}

BqsIndex.layout = {
    breadcrumbs: [{ title: 'BQS', href: index() }],
};
