import { Head, router } from '@inertiajs/react';
import {
    KeyRound,
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import ListToolbar from '@/components/admin/list-toolbar';
import Pagination from '@/components/admin/pagination';
import SortableHeader, { nextSort } from '@/components/admin/sortable-header';
import UserFormDialog from '@/components/admin/user-form-dialog';
import UserPasswordDialog from '@/components/admin/user-password-dialog';
import UserRoleDialog from '@/components/admin/user-role-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/users';
import type {
    DesignationFilterOption,
    DesignationOption,
    GenderOption,
    Paginated,
    StatusOption,
    UserFilters,
    UserListItem,
} from '@/types';

/** Human labels for the searchable column names the server allow-lists. */
const SEARCH_LABELS: Record<string, string> = {
    name: 'Name',
    employee_id: 'Employee ID',
    email: 'Email',
    personal_mobile_no: 'Personal mobile',
    official_mobile_no: 'Official mobile',
    official_extension_no: 'Extension',
};

type Props = {
    users: Paginated<UserListItem>;
    roles: string[];
    genders: GenderOption[];
    /** `RecordStatus::options()` — drives both the form and the filter. */
    statuses: StatusOption[];
    /** What the create/edit modal may offer. */
    designations: DesignationOption[];
    /** What the filter dropdown lists — retired designations included. */
    designationFilters: DesignationFilterOption[];
    sortable: string[];
    searchable: string[];
    filters: UserFilters;
    counts: { active: number; trashed: number };
};

export default function UsersIndex({
    users,
    roles,
    genders,
    statuses,
    designations,
    designationFilters,
    sortable,
    searchable,
    filters,
    counts,
}: Props) {
    const canCreate = useCan('admin.users.create');
    const canUpdate = useCan('admin.users.update');
    const canDelete = useCan('admin.users.delete');
    const canRestore = useCan('admin.users.restore');
    const canForceDelete = useCan('admin.users.force-delete');
    const canResetPassword = useCan('admin.users.reset-password');
    const canAssignRoles = useCan('admin.users.assign-roles');

    const isHistorical = filters.filter === 'trashed';

    // Any filter change resets to page 1 — staying on page 9 of a result set
    // that now has two pages would show an empty table.
    const visit = (next: Partial<UserFilters>) =>
        router.get(
            index({ query: { ...filters, ...next, page: undefined } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const toggleSort = (column: string) => visit(nextSort(filters, column));

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: toggleSort,
    });

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Everyone who can sign in. Deleting a user moves them to History, where they can be restored or permanently removed."
                    />

                    {canCreate && !isHistorical && (
                        <UserFormDialog
                            submit={UserController.store.form()}
                            genders={genders}
                            statuses={statuses}
                            designations={designations}
                            roles={roles}
                            title="New user"
                            description="The employee ID is the login name. The user is not emailed — hand the password over yourself."
                            submitLabel="Create user"
                        >
                            <Button data-test="new-user">
                                <Plus /> New user
                            </Button>
                        </UserFormDialog>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div role="tablist" className="tabs tabs-box">
                        <button
                            type="button"
                            role="tab"
                            className={`tab ${isHistorical ? '' : 'tab-active'}`}
                            onClick={() => visit({ filter: 'active' })}
                        >
                            Active
                            <span className="ml-2 badge badge-sm">
                                {counts.active}
                            </span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            className={`tab ${isHistorical ? 'tab-active' : ''}`}
                            onClick={() => visit({ filter: 'trashed' })}
                            data-test="historical-tab"
                        >
                            Historical
                            <span className="ml-2 badge badge-sm">
                                {counts.trashed}
                            </span>
                        </button>
                    </div>
                </div>

                <ListToolbar
                    filters={filters}
                    searchable={searchable}
                    searchLabels={SEARCH_LABELS}
                    onChange={visit}
                    onClear={() =>
                        visit({
                            search: '',
                            gender: '',
                            designation: '',
                            status: '',
                        })
                    }
                    controls={[
                        {
                            label: 'Gender',
                            ariaLabel: 'Filter by gender',
                            width: 'w-40',
                            value: filters.gender,
                            onSelect: (gender) => visit({ gender }),
                            options: [
                                { value: '', label: 'All genders' },
                                ...genders,
                            ],
                        },
                        {
                            label: 'Designation',
                            ariaLabel: 'Filter by designation',
                            testId: 'designation-filter',
                            width: 'w-52',
                            value: filters.designation,
                            onSelect: (designation) => visit({ designation }),
                            /* Deactivated designations are listed too: a
                               retired title still has holders and they have to
                               be findable. Values are stringified to match
                               `filters.designation`, which arrives from the
                               query string. */
                            options: [
                                { value: '', label: 'All designations' },
                                ...designationFilters.map((designation) => ({
                                    value: String(designation.value),
                                    label: designation.label,
                                })),
                            ],
                        },
                        {
                            label: 'Status',
                            ariaLabel: 'Filter by status',
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
                                <SortableHeader {...sortProps('employee_id')}>
                                    Employee ID
                                </SortableHeader>
                                <SortableHeader {...sortProps('name')}>
                                    Name
                                </SortableHeader>
                                {/* Not sortable: ordering by designation means
                                    ordering by a joined column, a query shape
                                    this list does not have. Filter instead. */}
                                <th className="hidden lg:table-cell">
                                    Designation
                                </th>
                                <th className="hidden md:table-cell">
                                    Contact
                                </th>
                                <th>Roles</th>
                                <th>{isHistorical ? 'Deleted' : 'Status'}</th>
                                {/* Eight columns plus four action buttons
                                    overflow a laptop viewport; the least
                                    identifying ones give way first. */}
                                <SortableHeader
                                    {...sortProps('created_at')}
                                    className="hidden xl:table-cell"
                                >
                                    Added
                                </SortableHeader>
                                <th className="w-40" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="text-center text-base-content/60"
                                    >
                                        {isHistorical
                                            ? 'No deleted users.'
                                            : 'No users match these filters. Search matches from the start of the field.'}
                                    </td>
                                </tr>
                            )}

                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td className="font-mono">
                                        {user.employee_id}
                                    </td>

                                    <td>
                                        <div className="font-medium">
                                            {user.name}
                                            {user.is_self && (
                                                <span className="ml-2 badge badge-sm badge-neutral">
                                                    you
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-base-content/60">
                                            {user.email}
                                        </div>
                                    </td>

                                    <td className="hidden text-xs lg:table-cell">
                                        {user.designation ?? (
                                            <span className="text-base-content/50">
                                                —
                                            </span>
                                        )}
                                    </td>

                                    <td className="hidden text-xs text-base-content/70 md:table-cell">
                                        {user.personal_mobile_no ?? '—'}
                                        {user.official_extension_no && (
                                            <div>
                                                ext.{' '}
                                                {user.official_extension_no}
                                            </div>
                                        )}
                                    </td>

                                    <td>
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length === 0 && (
                                                <span className="text-xs text-base-content/50">
                                                    none
                                                </span>
                                            )}

                                            {user.roles.map((role) => (
                                                <span
                                                    key={role}
                                                    className="badge badge-ghost font-mono badge-sm"
                                                >
                                                    {role}
                                                </span>
                                            ))}
                                        </div>
                                    </td>

                                    <td>
                                        {isHistorical ? (
                                            <span className="text-xs text-base-content/60">
                                                {user.deleted_at}
                                            </span>
                                        ) : (
                                            <span
                                                className={`badge badge-sm ${user.status === 'A' ? 'badge-success' : 'badge-warning'}`}
                                            >
                                                {user.status === 'A'
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        )}
                                    </td>

                                    <td className="hidden text-xs text-base-content/60 xl:table-cell">
                                        {user.created_at}
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {isHistorical ? (
                                                <>
                                                    {canRestore && (
                                                        <ConfirmActionDialog
                                                            submit={UserController.restore.form(
                                                                user.id,
                                                            )}
                                                            title={`Restore ${user.name}?`}
                                                            description="They return to the active list with the roles they had, and can sign in again."
                                                            confirmLabel="Restore"
                                                            confirmVariant="default"
                                                            testId="restore-user"
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Restore ${user.name}`}
                                                                data-test="restore-user"
                                                            >
                                                                <RotateCcw />
                                                            </Button>
                                                        </ConfirmActionDialog>
                                                    )}

                                                    {canForceDelete && (
                                                        <ConfirmActionDialog
                                                            submit={UserController.forceDelete.form(
                                                                user.id,
                                                            )}
                                                            title={`Permanently delete ${user.name}?`}
                                                            description="The record is destroyed. Their employee ID and email become free to reuse. This cannot be undone."
                                                            confirmLabel="Delete permanently"
                                                            disabled={
                                                                user.is_self ||
                                                                user.is_last_super_admin
                                                            }
                                                            testId="force-delete-user"
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                disabled={
                                                                    user.is_self ||
                                                                    user.is_last_super_admin
                                                                }
                                                                aria-label={`Permanently delete ${user.name}`}
                                                                data-test="force-delete-user"
                                                            >
                                                                <Trash2 className="text-error" />
                                                            </Button>
                                                        </ConfirmActionDialog>
                                                    )}
                                                </>
                                            ) : (
                                                <>
                                                    {canUpdate && (
                                                        <UserFormDialog
                                                            submit={UserController.update.form(
                                                                user.id,
                                                            )}
                                                            genders={genders}
                                                            statuses={statuses}
                                                            designations={
                                                                designations
                                                            }
                                                            user={user}
                                                            title={`Edit ${user.name}`}
                                                            description="Roles and passwords are changed with their own actions."
                                                            submitLabel="Save changes"
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Edit ${user.name}`}
                                                                data-test="edit-user"
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                        </UserFormDialog>
                                                    )}

                                                    {canAssignRoles && (
                                                        <UserRoleDialog
                                                            submit={UserController.updateRoles.form(
                                                                user.id,
                                                            )}
                                                            user={user}
                                                            roles={roles}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                disabled={
                                                                    user.is_self
                                                                }
                                                                aria-label={`Roles for ${user.name}`}
                                                                data-test="user-roles"
                                                            >
                                                                <ShieldCheck />
                                                            </Button>
                                                        </UserRoleDialog>
                                                    )}

                                                    {canResetPassword && (
                                                        <UserPasswordDialog
                                                            submit={UserController.updatePassword.form(
                                                                user.id,
                                                            )}
                                                            user={user}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Set password for ${user.name}`}
                                                                data-test="user-password"
                                                            >
                                                                <KeyRound />
                                                            </Button>
                                                        </UserPasswordDialog>
                                                    )}

                                                    {canDelete && (
                                                        <ConfirmActionDialog
                                                            submit={UserController.destroy.form(
                                                                user.id,
                                                            )}
                                                            title={`Delete ${user.name}?`}
                                                            description="They move to the Historical tab and can no longer sign in. Their employee ID stays reserved until they are permanently deleted."
                                                            confirmLabel="Delete"
                                                            disabled={
                                                                user.is_self ||
                                                                user.is_last_super_admin
                                                            }
                                                            testId="delete-user"
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                disabled={
                                                                    user.is_self ||
                                                                    user.is_last_super_admin
                                                                }
                                                                aria-label={`Delete ${user.name}`}
                                                                data-test="delete-user"
                                                            >
                                                                <Trash2 className="text-error" />
                                                            </Button>
                                                        </ConfirmActionDialog>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={users} />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
