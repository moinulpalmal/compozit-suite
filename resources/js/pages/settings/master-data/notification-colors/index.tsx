import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import NotificationColorController from '@/actions/App/Http/Controllers/Settings/NotificationColorController';
import Heading from '@/components/heading';
import NotificationColorFormDialog from '@/components/settings/notification-color-form-dialog';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ConfirmDeleteDialog from '@/components/shared/confirm-delete-dialog';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index } from '@/routes/settings/master-data/notification-colors';
import type {
    Filterable,
    NotificationColorFilters,
    NotificationColorListItem,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    notificationColors: Paginated<NotificationColorListItem>;
    statuses: StatusOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: NotificationColorFilters;
};

/**
 * The Settings module's first master-data surface.
 *
 * One page with modals — the `admin/designations` shape (ARCHITECTURE.md §5),
 * and the same shared list apparatus (§8.6). It renders under plain `AppLayout`,
 * **not** the account-settings shell: `app.tsx` matches that shell against three
 * named pages rather than the `settings/` prefix, precisely so a full-width
 * table like this one has somewhere to go (§8.1).
 *
 * Delete is refused when a TNA template paints a milestone with the colour, the
 * same way a designation somebody holds is refused. The server decides and answers
 * with a `warning` toast — this page always offers the button, because a client
 * that predicted the refusal would need the holder count on every row and would go
 * stale the moment someone else edited a template.
 */
export default function NotificationColorsIndex({
    notificationColors,
    statuses,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canCreate = useCan('settings.master-data.create');
    const canUpdate = useCan('settings.master-data.update');
    const canDelete = useCan('settings.master-data.delete');

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['notificationColors', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Notification colours" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Notification colours"
                        description="The colours a notification can be raised in, and how long each is kept. Reference data — other modules point at these, they never copy them."
                    />

                    {canCreate && (
                        <NotificationColorFormDialog
                            submit={NotificationColorController.store.form()}
                            statuses={statuses}
                            title="New notification colour"
                            description="It becomes selectable straight away."
                            submitLabel="Create colour"
                        >
                            <Button data-test="new-notification-color">
                                <Plus /> New colour
                            </Button>
                        </NotificationColorFormDialog>
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
                                <SortableHeader {...sortProps('color_code')}>
                                    Colour
                                </SortableHeader>
                                <SortableHeader
                                    {...sortProps('retention_days')}
                                >
                                    Retention
                                </SortableHeader>
                                <SortableHeader {...sortProps('status')}>
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
                                        column: 'name',
                                        label: 'name',
                                    },
                                    {
                                        type: 'text',
                                        column: 'color_code',
                                        label: 'colour',
                                    },
                                    /* Not filterable: an exact-match cell on a
                                       duration answers a question nobody asks.
                                       See `NotificationColor::FILTERABLE`. */
                                    { type: 'none' },
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
                                ]}
                            />
                        </thead>
                        <tbody>
                            {notificationColors.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-center text-base-content/60"
                                    >
                                        No notification colours match these
                                        filters.
                                    </td>
                                </tr>
                            )}

                            {notificationColors.data.map((color) => (
                                <tr key={color.id}>
                                    <td className="font-medium">
                                        {color.name}
                                    </td>

                                    <td>
                                        <span className="flex items-center gap-2">
                                            <span
                                                className="inline-block size-4 shrink-0 rounded border border-base-300"
                                                style={{
                                                    backgroundColor:
                                                        color.color_code,
                                                }}
                                                aria-hidden="true"
                                            />
                                            <span className="font-mono text-xs">
                                                {color.color_code}
                                            </span>
                                        </span>
                                    </td>

                                    <td className="text-xs text-base-content/70 tabular-nums">
                                        {color.retention_days} days
                                    </td>

                                    <td>
                                        <span
                                            className={`badge badge-sm ${color.status === 'A' ? 'badge-success' : 'badge-warning'}`}
                                        >
                                            {color.status === 'A'
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <NotificationColorFormDialog
                                                    submit={NotificationColorController.update.form(
                                                        color.id,
                                                    )}
                                                    statuses={statuses}
                                                    notificationColor={color}
                                                    title={`Edit ${color.name}`}
                                                    description="Changing the colour updates it everywhere — records reference the row, not a copy of its hex."
                                                    submitLabel="Save changes"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${color.name}`}
                                                        data-test="edit-notification-color"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                </NotificationColorFormDialog>
                                            )}

                                            {canDelete && (
                                                <ConfirmDeleteDialog
                                                    submit={NotificationColorController.destroy.form(
                                                        color.id,
                                                    )}
                                                    title={`Delete ${color.name}?`}
                                                    description="This removes the colour for good. It cannot be undone — deactivate it instead to retire it from the pickers."
                                                    testId="delete-notification-color"
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={notificationColors} />
            </div>
        </>
    );
}

NotificationColorsIndex.layout = {
    breadcrumbs: [{ title: 'Notification colours', href: index() }],
};
