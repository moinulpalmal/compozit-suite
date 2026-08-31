import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import Heading from '@/components/heading';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ConfirmDeleteDialog from '@/components/shared/confirm-delete-dialog';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { create, edit, index } from '@/routes/admin/permissions';
import type {
    Filterable,
    ModuleOption,
    Paginated,
    PermissionFilters,
    PermissionListItem,
} from '@/types';

type Props = {
    permissions: Paginated<PermissionListItem>;
    modules: ModuleOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: PermissionFilters;
};

/**
 * Permissions as a flat, filterable table.
 *
 * This page used to group rows under module headings, filtered client-side over
 * the whole catalogue. **Grouping and pagination cannot both hold** — a group
 * would be cut across a page boundary, and the remainder would open the next
 * page under no heading. So the module became a column with its own filter cell,
 * served by `Permission::scopeModule()`.
 *
 * The role form's permission picker still groups by module. That is a different
 * query (`PermissionService::groupedByModule()`) and is deliberately untouched.
 */
export default function PermissionsIndex({
    permissions,
    modules,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canCreate = useCan('admin.permissions.create');
    const canUpdate = useCan('admin.permissions.update');
    const canDelete = useCan('admin.permissions.delete');

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['permissions', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    return (
        <>
            <Head title="Permissions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Permissions"
                        description="Named module.resource.action abilities. Roles hand them out; policies and route middleware enforce them."
                    />

                    {canCreate && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New permission
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
                                    Permission
                                </SortableHeader>
                                <th>Module</th>
                                <th>Roles</th>
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
                                        label: 'permission',
                                    },
                                    {
                                        type: 'select',
                                        column: 'module',
                                        label: 'module',
                                        testId: 'module-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...modules,
                                        ],
                                    },
                                    { type: 'none' },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>
                        <tbody>
                            {permissions.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-center text-base-content/60"
                                    >
                                        No permissions match these filters.
                                    </td>
                                </tr>
                            )}

                            {permissions.data.map((permission) => (
                                <tr key={permission.id}>
                                    <td className="font-mono">
                                        {permission.name}
                                    </td>

                                    <td>
                                        <span className="badge badge-ghost badge-sm">
                                            {permission.module}
                                        </span>
                                    </td>

                                    <td>
                                        <div className="flex flex-wrap gap-1">
                                            {permission.roles.length === 0 ? (
                                                <span className="text-base-content/60">
                                                    —
                                                </span>
                                            ) : (
                                                permission.roles.map((role) => (
                                                    <span
                                                        key={role}
                                                        className="badge badge-ghost badge-sm"
                                                    >
                                                        {role}
                                                    </span>
                                                ))
                                            )}
                                        </div>
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Edit ${permission.name}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(
                                                            permission.id,
                                                        )}
                                                    >
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                            )}

                                            {canDelete && (
                                                <ConfirmDeleteDialog
                                                    submit={PermissionController.destroy.form(
                                                        permission.id,
                                                    )}
                                                    title={`Delete ${permission.name}?`}
                                                    description="Every role loses this ability, and any route or policy still naming it will deny access."
                                                    testId="delete-permission"
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={permissions} />
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: 'Permissions', href: index() }],
};
