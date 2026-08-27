import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import DesignationController from '@/actions/App/Http/Controllers/Admin/DesignationController';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import DesignationFormDialog from '@/components/admin/designation-form-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/designations';
import type { DesignationListItem, StatusOption } from '@/types';

type Props = {
    designations: DesignationListItem[];
    statuses: StatusOption[];
};

/**
 * Job titles, on one page with modals — the `admin/users` shape.
 *
 * There are no Active/Historical tabs: a designation is retired by setting its
 * status, not by soft-deleting it, so deactivate and delete are two different
 * verbs here. Delete is refused while anybody holds the title, which is why the
 * row shows its holder count.
 */
export default function DesignationsIndex({ designations, statuses }: Props) {
    const canCreate = useCan('admin.designations.create');
    const canUpdate = useCan('admin.designations.update');
    const canDelete = useCan('admin.designations.delete');

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

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Short form</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th className="w-24" />
                            </tr>
                        </thead>
                        <tbody>
                            {designations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-center text-base-content/60"
                                    >
                                        No designations yet.
                                    </td>
                                </tr>
                            )}

                            {designations.map((designation) => (
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
            </div>
        </>
    );
}

DesignationsIndex.layout = {
    breadcrumbs: [{ title: 'Designations', href: index() }],
};
