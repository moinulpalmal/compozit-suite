import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import DesignationController from '@/actions/App/Http/Controllers/Admin/DesignationController';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import DesignationFormDialog from '@/components/admin/designation-form-dialog';
import ListToolbar from '@/components/admin/list-toolbar';
import Pagination from '@/components/admin/pagination';
import SortableHeader, { nextSort } from '@/components/admin/sortable-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/designations';
import type {
    DesignationFilters,
    DesignationListItem,
    Paginated,
    StatusOption,
} from '@/types';

/** Human labels for the searchable column names the server allow-lists. */
const SEARCH_LABELS: Record<string, string> = {
    name: 'Name',
    short_form: 'Short form',
};

type Props = {
    designations: Paginated<DesignationListItem>;
    statuses: StatusOption[];
    sortable: string[];
    searchable: string[];
    filters: DesignationFilters;
};

/**
 * Job titles, on one page with modals — the `admin/users` shape.
 *
 * There are no Active/Historical tabs: a designation is retired by setting its
 * status, not by soft-deleting it, so deactivate and delete are two different
 * verbs here. Delete is refused while anybody holds the title, which is why the
 * row shows its holder count.
 *
 * Paginated, searchable and sortable like every Admin list — ARCHITECTURE.md
 * §8.6. Note this pagination does **not** reach the user form's designation
 * picker: that is a separate query which must offer every assignable title.
 */
export default function DesignationsIndex({
    designations,
    statuses,
    sortable,
    searchable,
    filters,
}: Props) {
    const canCreate = useCan('admin.designations.create');
    const canUpdate = useCan('admin.designations.update');
    const canDelete = useCan('admin.designations.delete');

    // Any filter change resets to page 1 — staying on page 9 of a result set
    // that now has two pages would show an empty table.
    const visit = (next: Partial<DesignationFilters>) =>
        router.get(
            index({ query: { ...filters, ...next, page: undefined } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Designations" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Designations"
                        description="Job titles a user can hold. A designation is a label — it grants no access of its own; roles do that."
                    />

                    {canCreate && (
                        <DesignationFormDialog
                            submit={DesignationController.store.form()}
                            statuses={statuses}
                            title="New designation"
                            description="It becomes selectable on the user form straight away."
                            submitLabel="Create designation"
                        >
                            <Button data-test="new-designation">
                                <Plus /> New designation
                            </Button>
                        </DesignationFormDialog>
                    )}
                </div>

                <ListToolbar
                    filters={filters}
                    searchable={searchable}
                    searchLabels={SEARCH_LABELS}
                    onChange={visit}
                    onClear={() => visit({ search: '', status: '' })}
                    controls={[
                        {
                            label: 'Status',
                            ariaLabel: 'Filter by status',
                            testId: 'status-filter',
                            width: 'w-36',
                            value: filters.status,
                            onSelect: (status) => visit({ status }),
                            options: [
                                { value: '', label: 'All statuses' },
                                ...statuses,
                            ],
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <SortableHeader {...sortProps('name')}>
                                    Name
                                </SortableHeader>
                                <SortableHeader {...sortProps('short_form')}>
                                    Short form
                                </SortableHeader>
                                <SortableHeader {...sortProps('status')}>
                                    Status
                                </SortableHeader>
                                <th>Users</th>
                                <th className="w-24" />
                            </tr>
                        </thead>
                        <tbody>
                            {designations.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-center text-base-content/60"
                                    >
                                        No designations match these filters.
                                        Search matches from the start of the
                                        field.
                                    </td>
                                </tr>
                            )}

                            {designations.data.map((designation) => (
                                <tr key={designation.id}>
                                    <td className="font-medium">
                                        {designation.name}
                                    </td>

                                    <td className="font-mono text-xs">
                                        {designation.short_form ?? (
                                            <span className="text-base-content/50">
                                                —
                                            </span>
                                        )}
                                    </td>

                                    <td>
                                        <span
                                            className={`badge badge-sm ${designation.status === 'A' ? 'badge-success' : 'badge-warning'}`}
                                        >
                                            {designation.status === 'A'
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>

                                    <td className="text-xs text-base-content/70">
                                        {designation.users_count}
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <DesignationFormDialog
                                                    submit={DesignationController.update.form(
                                                        designation.id,
                                                    )}
                                                    statuses={statuses}
                                                    designation={designation}
                                                    title={`Edit ${designation.name}`}
                                                    description="Renaming updates it everywhere — users reference the designation, not a copy of its name."
                                                    submitLabel="Save changes"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${designation.name}`}
                                                        data-test="edit-designation"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                </DesignationFormDialog>
                                            )}

                                            {canDelete && (
                                                <ConfirmActionDialog
                                                    submit={DesignationController.destroy.form(
                                                        designation.id,
                                                    )}
                                                    title={`Delete ${designation.name}?`}
                                                    description="This removes the designation for good. It cannot be undone."
                                                    confirmLabel="Delete"
                                                    disabled={
                                                        !designation.is_deletable
                                                    }
                                                    testId="delete-designation"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        disabled={
                                                            !designation.is_deletable
                                                        }
                                                        aria-label={
                                                            designation.is_deletable
                                                                ? `Delete ${designation.name}`
                                                                : `${designation.name} is held by ${designation.users_count} users and cannot be deleted`
                                                        }
                                                        title={
                                                            designation.is_deletable
                                                                ? undefined
                                                                : 'Held by a user — deactivate it instead.'
                                                        }
                                                        data-test="delete-designation"
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

                <Pagination page={designations} />
            </div>
        </>
    );
}

DesignationsIndex.layout = {
    breadcrumbs: [{ title: 'Designations', href: index() }],
};
