import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleForm from '@/components/admin/role-form';
import Heading from '@/components/heading';
import { create, index } from '@/routes/admin/roles';
import type { PermissionGroups } from '@/types';

export default function RoleCreate({
    permissions,
}: {
    permissions: PermissionGroups;
}) {
    return (
        <>
            <Head title="New role" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New role"
                    description="Name the role, then pick the permissions it grants."
                />

                <RoleForm
                    submit={RoleController.store.form()}
                    permissions={permissions}
                    submitLabel="Create role"
                />
            </div>
        </>
    );
}

RoleCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'New role', href: create() },
    ],
};
