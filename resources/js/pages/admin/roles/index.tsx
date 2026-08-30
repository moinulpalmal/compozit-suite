import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import ColumnFilterRow from '@/components/admin/column-filter-row';
import ConfirmDeleteDialog from '@/components/admin/confirm-delete-dialog';
import ListToolbar from '@/components/admin/list-toolbar';
import Pagination from '@/components/admin/pagination';
import SortableHeader, { nextSort } from '@/components/admin/sortable-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { create, edit, index } from '@/routes/admin/roles';
import type { Filterable, Paginated, RoleFilters, RoleListItem } from '@/types';

type Props = {
    roles: Paginated<RoleListItem>;
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: RoleFilters;
};

export default function RolesIndex({
    roles,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canCreate = useCan('admin.roles.create');
    const canUpdate = useCan('admin.roles.update');
    const canDelete = useCan('admin.roles.delete');

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['roles', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Roles" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="A role is a named bundle of permissions. Assign abilities here, never by checking a role name in code."
                    />

                    {canCreate && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New role
                            </Link>
                        </Button>
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
                    <table className="table">
                        <thead>
                            <tr>
                                <SortableHeader {...sortProps('name')}>
                                    Role
                                </SortableHeader>
                                {/* Aggregates, not columns — neither sorting
                                    nor filtering by them goes through the
                                    allow-list path. See Role::SORTABLE and
                                    Role::FILTERABLE. */}
                                <th className="text-right">Permissions</th>
                                <th className="text-right">Users</th>
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
                                        label: 'role',
                                    },
                                    { type: 'none' },
                                    { type: 'none' },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>
                        <tbody>
                            {roles.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-center text-base-content/60"
                                    >
                                        No roles match these filters.
                                    </td>
                                </tr>
                            )}

                            {roles.data.map((role) => (
                                <tr key={role.id}>
                                    <td className="font-mono">
                                        {role.name}
                                        {role.is_super_admin && (
                                            <span className="ml-2 badge badge-sm badge-neutral">
                                                bypasses all checks
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {role.is_super_admin
                                            ? 'all'
                                            : role.permissions_count}
                                    </td>
                                    <td className="text-right tabular-nums">
                                        {role.users_count}
                                    </td>
                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate &&
                                                !role.is_super_admin && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${role.name}`}
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(role.id)}
                                                        >
                                                            <Pencil />
                                                        </Link>
                                                    </Button>
                                                )}

                                            {canDelete && (
                                                <ConfirmDeleteDialog
                                                    submit={RoleController.destroy.form(
                                                        role.id,
                                                    )}
                                                    title={`Delete ${role.name}?`}
                                                    description="Users holding this role lose the abilities it grants. This cannot be undone."
                                                    disabled={
                                                        !role.is_deletable
                                                    }
                                                    testId="delete-role"
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={roles} />
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
