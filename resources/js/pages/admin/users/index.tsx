import { Head } from '@inertiajs/react';
import {
    KeyRound,
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    Store,
    Trash2,
} from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ColumnFilterRow from '@/components/admin/column-filter-row';
import ConfirmActionDialog from '@/components/admin/confirm-action-dialog';
import ListToolbar from '@/components/admin/list-toolbar';
import Pagination from '@/components/admin/pagination';
import SortableHeader, { nextSort } from '@/components/admin/sortable-header';
import UserBuyerAccessDialog from '@/components/admin/user-buyer-access-dialog';
import UserFormDialog from '@/components/admin/user-form-dialog';
import UserPasswordDialog from '@/components/admin/user-password-dialog';
import UserRoleDialog from '@/components/admin/user-role-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index } from '@/routes/admin/users';
import type {
    DesignationFilterOption,
    DesignationOption,
    Filterable,
    GenderOption,
    Paginated,
    StatusOption,
    UserFilters,
    UserListItem,
} from '@/types';

type Props = {
    users: Paginated<UserListItem>;
    roles: string[];
    genders: GenderOption[];
    /** `RecordStatus::options()` — drives both the form and the filter. */
    statuses: StatusOption[];
    /** What the create/edit modal may offer. */
    designations: DesignationOption[];
    /** What the filter cell lists — retired designations included. */
    designationFilters: DesignationFilterOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
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
    filterable,
    perPageOptions,
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
    // The controller ships buyer data on the same permission; without it the
    // column would render zeroes for everybody.
    const canViewBuyerAccess = useCan('admin.buyer-access.view');
    const canAssignBuyers = useCan('admin.buyer-access.update');

    const isHistorical = filters.view === 'trashed';

    /*
     * `designations` is in the partial reload because it is derived from the
     * rows on screen — it offers every retired title the *current page* still
     * holds, so leaving it out would let the edit modal go stale after a filter.
     */
    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['users', 'filters', 'designations'],
    });

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

                <ListToolbar
                    perPage={filters.per_page}
                    perPageOptions={perPageOptions}
                    onPerPage={(per_page) => visit({ per_page })}
                    onClear={clear}
                    hasActiveFilter={hasActiveFilter}
                >
                    {/* Which record set, not which column value — so it stays
                        here rather than becoming a filter cell. */}
                    <div role="tablist" className="tabs tabs-box">
                        <button
                            type="button"
                            role="tab"
                            className={`tab ${isHistorical ? '' : 'tab-active'}`}
                            onClick={() => visit({ view: 'active' })}
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
                            onClick={() => visit({ view: 'trashed' })}
                            data-test="historical-tab"
                        >
                            Historical
                            <span className="ml-2 badge badge-sm">
                                {counts.trashed}
                            </span>
                        </button>
                    </div>
                </ListToolbar>

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
                                {/* Not sortable and not filterable: it is a
                                    `withCount` aggregate over a pivot, and the
                                    all-access flag is not a column of it. */}
                                {canViewBuyerAccess && (
                                    <th className="hidden lg:table-cell">
                                        Buyers
                                    </th>
                                )}
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
                                <th className="w-48" />
                            </tr>

                            <ColumnFilterRow
                                filterable={filterable}
                                draft={draft}
                                onFilter={setFilter}
                                cells={[
                                    {
                                        type: 'text',
                                        column: 'employee_id',
                                        label: 'employee ID',
                                    },
                                    /* One heading, two columns underneath it —
                                       so the cell stacks, and each box names
                                       itself rather than repeating the shared
                                       match hint. */
                                    {
                                        type: 'stack',
                                        cells: [
                                            {
                                                type: 'text',
                                                column: 'name',
                                                label: 'name',
                                            },
                                            {
                                                type: 'text',
                                                column: 'email',
                                                label: 'email',
                                                placeholder: 'Email…',
                                            },
                                        ],
                                    },
                                    {
                                        type: 'select',
                                        column: 'designation_id',
                                        label: 'designation',
                                        testId: 'designation-filter',
                                        className: 'hidden lg:table-cell',
                                        /* Deactivated designations are listed
                                           too: a retired title still has
                                           holders and they have to be findable.
                                           Values are stringified to match the
                                           filter, which arrives as a string. */
                                        options: [
                                            { value: '', label: 'All' },
                                            ...designationFilters.map(
                                                (designation) => ({
                                                    value: String(
                                                        designation.value,
                                                    ),
                                                    label: designation.label,
                                                }),
                                            ),
                                        ],
                                    },
                                    {
                                        type: 'stack',
                                        className: 'hidden md:table-cell',
                                        cells: [
                                            {
                                                type: 'text',
                                                column: 'personal_mobile_no',
                                                label: 'personal mobile',
                                                placeholder: 'Personal…',
                                            },
                                            {
                                                type: 'text',
                                                column: 'official_mobile_no',
                                                label: 'official mobile',
                                                placeholder: 'Official…',
                                            },
                                            {
                                                type: 'text',
                                                column: 'official_extension_no',
                                                label: 'extension',
                                                placeholder: 'Ext…',
                                            },
                                        ],
                                    },
                                    { type: 'none' },
                                    ...(canViewBuyerAccess
                                        ? [
                                              {
                                                  type: 'none' as const,
                                                  className:
                                                      'hidden lg:table-cell',
                                              },
                                          ]
                                        : []),
                                    /* The column itself becomes "Deleted" on
                                       the historical tab, where a status filter
                                       would sit under a heading it does not
                                       describe. */
                                    isHistorical
                                        ? { type: 'none' }
                                        : {
                                              type: 'select',
                                              column: 'status',
                                              label: 'status',
                                              testId: 'status-filter',
                                              options: [
                                                  { value: '', label: 'All' },
                                                  ...statuses,
                                              ],
                                          },
                                    {
                                        type: 'none',
                                        className: 'hidden xl:table-cell',
                                    },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canViewBuyerAccess ? 9 : 8}
                                        className="text-center text-base-content/60"
                                    >
                                        {isHistorical
                                            ? 'No deleted users.'
                                            : 'No users match these filters.'}
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

                                    {canViewBuyerAccess && (
                                        <td className="hidden lg:table-cell">
                                            {user.all_buyer_access ? (
                                                <span
                                                    className="badge badge-sm badge-info"
                                                    title="Every buyer, including ones added later"
                                                >
                                                    All
                                                </span>
                                            ) : (user.buyers_count ?? 0) ===
                                              0 ? (
                                                <span
                                                    className="text-xs text-base-content/50"
                                                    title="Sees no buyer-owned records at all"
                                                >
                                                    — none —
                                                </span>
                                            ) : (
                                                <span className="text-xs text-base-content/70">
                                                    {user.buyers_count}
                                                </span>
                                            )}
                                        </td>
                                    )}

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

                                                    {canAssignBuyers &&
                                                        canViewBuyerAccess && (
                                                            <UserBuyerAccessDialog
                                                                submit={UserController.updateBuyerAccess.form(
                                                                    user.id,
                                                                )}
                                                                user={user}
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    disabled={
                                                                        user.is_self
                                                                    }
                                                                    aria-label={`Buyer access for ${user.name}`}
                                                                    data-test="user-buyer-access"
                                                                >
                                                                    <Store />
                                                                </Button>
                                                            </UserBuyerAccessDialog>
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
