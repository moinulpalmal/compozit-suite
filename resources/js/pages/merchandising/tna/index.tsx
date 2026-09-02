import { Head, Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import Heading from '@/components/heading';
import TnaDateCell from '@/components/merchandising/tna-date-cell';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { useListFilters } from '@/hooks/use-list-filters';
import { show } from '@/routes/merchandising/purchase-orders';
import { index } from '@/routes/merchandising/tna';
import type {
    Filterable,
    MilestoneOption,
    Paginated,
    PurchaseOrderFilters,
    TnaListItem,
} from '@/types';

type Props = {
    orders: Paginated<TnaListItem>;
    milestones: MilestoneOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: PurchaseOrderFilters;
};

/**
 * The time-and-action board: every live order and when its milestones fall.
 *
 * **Read-only, and there is nothing to add.** The dates come from a template in
 * Settings and the ship date from the order, so both corrections are made where the
 * data lives. That is also why an unscheduled row shows a sentence rather than an
 * empty row — it names which of those two places to go.
 *
 * The milestone columns are built from the `milestones` prop rather than written
 * out here, so the twenty-sixth milestone is an enum case on the server and no
 * change to this file.
 */
export default function TnaIndex({
    orders,
    milestones,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['orders', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    // PO number, buyer, ship date, lead time, then one per milestone.
    const columnCount = 4 + milestones.length;

    return (
        <>
            <Head title="TNA" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="TNA"
                    description="When each milestone of a live purchase order falls. Dates are worked out from the TNA template covering the order's lead time — shipment date minus BQS date — and are never stored, so correcting a template corrects every order here."
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
                                <SortableHeader {...sortProps('po_number')}>
                                    PO number
                                </SortableHeader>
                                <th>Buyer</th>
                                <SortableHeader
                                    {...sortProps('vendor_ship_date')}
                                >
                                    Ship date
                                </SortableHeader>
                                <th className="text-right">Lead time</th>

                                {milestones.map((milestone) => (
                                    <th key={milestone.value}>
                                        {milestone.label}
                                    </th>
                                ))}
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
                                    { type: 'none' },
                                    /* Ship date, lead time and every milestone are
                                       computed per row after the page is fetched, so
                                       the database cannot narrow by them. See
                                       `TnaIndexRequest`. */
                                    { type: 'none' },
                                    { type: 'none' },
                                    ...milestones.map(
                                        () => ({ type: 'none' }) as const,
                                    ),
                                ]}
                            />
                        </thead>

                        <tbody>
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columnCount}
                                        className="text-center text-base-content/60"
                                    >
                                        No purchase orders match these filters.
                                    </td>
                                </tr>
                            )}

                            {orders.data.map((order) => (
                                <TnaRow
                                    key={order.id}
                                    order={order}
                                    milestones={milestones}
                                    columnCount={columnCount}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={orders} />
            </div>
        </>
    );
}

/**
 * One order, and either its schedule or the reason it has none.
 *
 * The reason renders as a second row spanning the table rather than crammed into a
 * cell: it is a sentence naming what to go and do, and truncating it to a column
 * width would defeat the point of having it.
 */
function TnaRow({
    order,
    milestones,
    columnCount,
}: {
    order: TnaListItem;
    milestones: MilestoneOption[];
    columnCount: number;
}) {
    const { tna } = order;

    const cellFor = (milestone: string) =>
        tna.milestones.find((cell) => cell.milestone === milestone);

    return (
        <>
            <tr className={tna.reason === null ? undefined : 'border-b-0'}>
                <td className="font-medium">
                    <Link
                        href={show(order.id)}
                        className="link link-hover"
                        data-test="tna-po-link"
                    >
                        {order.po_number}
                    </Link>
                </td>

                <td className="text-xs text-base-content/70">{order.buyer}</td>

                <td className="tabular-nums">{tna.ship_date ?? '—'}</td>

                <td
                    className="text-right tabular-nums"
                    title={
                        tna.template === null
                            ? undefined
                            : `Template: ${tna.template.name} (${tna.template.lead_time_from}–${tna.template.lead_time_to} days)`
                    }
                    data-test="tna-lead-time"
                >
                    {tna.lead_time_days === null
                        ? '—'
                        : `${tna.lead_time_days} days`}
                </td>

                {milestones.map((milestone) => {
                    const cell = cellFor(milestone.value);

                    return (
                        <td key={milestone.value}>
                            {cell === undefined ? (
                                <span className="text-base-content/30">—</span>
                            ) : (
                                <TnaDateCell cell={cell} />
                            )}
                        </td>
                    );
                })}
            </tr>

            {tna.reason !== null && (
                <tr>
                    <td
                        colSpan={columnCount}
                        className="pt-0 text-xs text-base-content/60"
                        data-test="tna-reason"
                    >
                        <span className="inline-flex items-center gap-1.5">
                            <CircleAlert className="size-3.5 shrink-0 text-warning" />
                            {tna.reason}
                        </span>
                    </td>
                </tr>
            )}
        </>
    );
}

TnaIndex.layout = {
    breadcrumbs: [{ title: 'TNA', href: index() }],
};
