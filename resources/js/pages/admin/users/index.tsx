import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    KeyRound,
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import Pagination from '@/components/admin/pagination';
import UserFormDialog from '@/components/admin/user-form-dialog';
import UserPasswordDialog from '@/components/admin/user-password-dialog';
import UserRoleDialog from '@/components/admin/user-role-dialog';
import UsersTableToolbar from '@/components/admin/users-table-toolbar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/users';
import type {
    GenderOption,
    Paginated,
    UserFilters,
    UserListItem,
} from '@/types';

type Props = {
    users: Paginated<UserListItem>;
    roles: string[];
    genders: GenderOption[];
    sortable: string[];
    searchable: string[];
    filters: UserFilters;
    counts: { active: number; trashed: number };
};

export default function UsersIndex({
    users,
    roles,
    genders,
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

    // Clicking the active column flips direction; a new column starts ascending.
    const toggleSort = (column: string) =>
        visit({
            sort: column,
            direction:
                filters.sort === column && filters.direction === 'asc'
                    ? 'desc'
                    : 'asc',
        });

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

                <UsersTableToolbar
                    filters={filters}
                    searchable={searchable}
                    genders={genders}
                    onChange={visit}
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
                                <th className="hidden md:table-cell">
                                    Contact
                                </th>
                                <th>Roles</th>
                                <th>{isHistorical ? 'Deleted' : 'Status'}</th>
                                {/* Seven columns plus four action buttons
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
                                        colSpan={7}
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
                                                className={`badge badge-sm ${user.approved ? 'badge-success' : 'badge-warning'}`}
                                            >
                                                {user.approved
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

/**
 * A `<th>` that sorts the list, or a plain one when the column is not
 * allow-listed by the server.
 */
function SortableHeader({
    column,
    sortable,
    filters,
    onSort,
    className,
    children,
}: {
    column: string;
    sortable: string[];
    filters: UserFilters;
    onSort: (column: string) => void;
    className?: string;
    children: ReactNode;
}) {
    if (!sortable.includes(column)) {
        return <th className={className}>{children}</th>;
    }

    const active = filters.sort === column;
    const Icon = !active
        ? ArrowUpDown
        : filters.direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <th
            className={className}
            aria-sort={
                active
                    ? filters.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                className="inline-flex cursor-pointer items-center gap-1 hover:text-base-content"
                onClick={() => onSort(column)}
                data-test={`sort-${column}`}
            >
                {children}
                <Icon className={active ? 'size-3' : 'size-3 opacity-40'} />
            </button>
        </th>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
