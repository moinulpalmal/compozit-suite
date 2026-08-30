import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import BuyerController from '@/actions/App/Http/Controllers/Admin/BuyerController';
import BuyerFormDialog from '@/components/admin/buyer-form-dialog';
import Heading from '@/components/heading';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ConfirmActionDialog from '@/components/shared/confirm-action-dialog';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index } from '@/routes/admin/buyers';
import type {
    BuyerFilters,
    BuyerListItem,
    Filterable,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    buyers: Paginated<BuyerListItem>;
    statuses: StatusOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: BuyerFilters;
};

/**
 * Buyers, on one page with modals — the `admin/designations` shape.
 *
 * A buyer is the unit every buyer-owned record is scoped by (ARCHITECTURE.md
 * §9.2), so deactivating and deleting mean different things here too:
 * deactivating removes it from the access picker and leaves its history alone.
 *
 * Access is granted on `admin/users`, not here — the Granted column is a count,
 * not a link, and deliberately excludes all-access users, who hold no row.
 */
export default function BuyersIndex({
    buyers,
    statuses,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canCreate = useCan('admin.buyers.create');
    const canUpdate = useCan('admin.buyers.update');
    const canDelete = useCan('admin.buyers.delete');

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['buyers', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Buyers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Buyers"
                        description="The customers this factory produces for. Every order, tech pack and booking belongs to one, and users only see the buyers they are granted."
                    />

                    {canCreate && (
                        <BuyerFormDialog
                            submit={BuyerController.store.form()}
                            statuses={statuses}
                            title="New buyer"
                            description="Anyone with access to all buyers can see it immediately. Individual grants are made on the Users screen."
                            submitLabel="Create buyer"
                        >
                            <Button data-test="new-buyer">
                                <Plus /> New buyer
                            </Button>
                        </BuyerFormDialog>
                    )}
                </div>

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
                                <SortableHeader {...sortProps('name')}>
                                    Name
                                </SortableHeader>
                                <SortableHeader {...sortProps('code')}>
                                    Code
                                </SortableHeader>
                                <SortableHeader {...sortProps('status')}>
                                    Status
                                </SortableHeader>
                                {/* Not filterable: `users_count` is a
                                    `withCount` aggregate, so narrowing by it
                                    needs `HAVING` rather than `WHERE`. */}
                                <th>Granted</th>
                                <th className="w-24" />
                            </tr>

                            <ColumnFilterRow
                                filterable={filterable}
                                draft={draft}
                                onFilter={setFilter}
                                cells={[
                                    {
                                        type: 'text',
                                        column: 'name',
                                        label: 'name',
                                    },
                                    {
                                        type: 'text',
                                        column: 'code',
                                        label: 'code',
                                    },
                                    {
                                        type: 'select',
                                        column: 'status',
                                        label: 'status',
                                        testId: 'status-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...statuses,
                                        ],
                                    },
                                    { type: 'none' },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>
                        <tbody>
                            {buyers.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-center text-base-content/60"
                                    >
                                        No buyers match these filters.
                                    </td>
                                </tr>
                            )}

                            {buyers.data.map((buyer) => (
                                <tr key={buyer.id}>
                                    <td className="font-medium">
                                        {buyer.name}
                                    </td>

                                    <td className="font-mono text-xs">
                                        {buyer.code ?? (
                                            <span className="text-base-content/50">
                                                —
                                            </span>
                                        )}
                                    </td>

                                    <td>
                                        <span
                                            className={`badge badge-sm ${buyer.status === 'A' ? 'badge-success' : 'badge-warning'}`}
                                        >
                                            {buyer.status === 'A'
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>

                                    <td
                                        className="text-xs text-base-content/70"
                                        title="Users granted this buyer individually. Anyone with access to all buyers is not counted."
                                    >
                                        {buyer.users_count}
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <BuyerFormDialog
                                                    submit={BuyerController.update.form(
                                                        buyer.id,
                                                    )}
                                                    statuses={statuses}
                                                    buyer={buyer}
                                                    title={`Edit ${buyer.name}`}
                                                    description="Renaming updates it everywhere — orders reference the buyer, not a copy of its name."
                                                    submitLabel="Save changes"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${buyer.name}`}
                                                        data-test="edit-buyer"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                </BuyerFormDialog>
                                            )}

                                            {canDelete && (
                                                <ConfirmActionDialog
                                                    submit={BuyerController.destroy.form(
                                                        buyer.id,
                                                    )}
                                                    title={`Delete ${buyer.name}?`}
                                                    description="The buyer is removed for good, and every grant of it is withdrawn. Deactivate it instead to keep its history and stop offering it."
                                                    confirmLabel="Delete"
                                                    testId="delete-buyer"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${buyer.name}`}
                                                        data-test="delete-buyer"
                                                    >
                                                        <Trash2 className="text-error" />
                                                    </Button>
                                                </ConfirmActionDialog>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={buyers} />
            </div>
        </>
    );
}

BuyersIndex.layout = {
    breadcrumbs: [{ title: 'Buyers', href: index() }],
};
