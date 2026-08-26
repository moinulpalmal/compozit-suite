import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import ConfirmDeleteDialog from '@/components/admin/confirm-delete-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { create, edit, index } from '@/routes/admin/permissions';
import type { PermissionListItem } from '@/types';

export default function PermissionsIndex({
    permissions,
}: {
    permissions: PermissionListItem[];
}) {
    const [filter, setFilter] = useState('');
    const canCreate = useCan('admin.permissions.create');
    const canUpdate = useCan('admin.permissions.update');
    const canDelete = useCan('admin.permissions.delete');

    const groups = useMemo(() => {
        const needle = filter.trim().toLowerCase();

        return permissions
            .filter((permission) => permission.name.includes(needle))
            .reduce<Record<string, PermissionListItem[]>>(
                (carry, permission) => {
                    carry[permission.module] = [
                        ...(carry[permission.module] ?? []),
                        permission,
                    ];

                    return carry;
                },
                {},
            );
    }, [permissions, filter]);

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

                <Input
                    type="search"
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder="Filter by name…"
                    aria-label="Filter permissions"
                    className="max-w-sm"
                />

                {Object.keys(groups).length === 0 && (
                    <p className="text-sm text-base-content/60">
                        No permissions match.
                    </p>
                )}

                {Object.entries(groups).map(([module, items]) => (
                    <section key={module} className="space-y-2">
                        <h3 className="text-sm font-semibold tracking-tight">
                            {module}
                        </h3>

                        <div className="overflow-x-auto rounded-box border border-base-300/70">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Permission</th>
                                        <th>Roles</th>
                                        <th className="w-24" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((permission) => (
                                        <tr key={permission.id}>
                                            <td className="font-mono">
                                                {permission.name}
                                            </td>
                                            <td>
                                                <div className="flex flex-wrap gap-1">
                                                    {permission.roles.length ===
                                                    0 ? (
                                                        <span className="text-base-content/60">
                                                            —
                                                        </span>
                                                    ) : (
                                                        permission.roles.map(
                                                            (role) => (
                                                                <span
                                                                    key={role}
                                                                    className="badge badge-ghost badge-sm"
                                                                >
                                                                    {role}
                                                                </span>
                                                            ),
                                                        )
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
                    </section>
                ))}
            </div>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [{ title: 'Permissions', href: index() }],
};
