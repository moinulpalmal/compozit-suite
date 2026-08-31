import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import RoleForm from '@/components/admin/role-form';
import Heading from '@/components/heading';
import { edit, index } from '@/routes/admin/roles';
import type { PermissionGroups, RoleDetail } from '@/types';

export default function RoleEdit({
    role,
    permissions,
}: {
    role: RoleDetail;
    permissions: PermissionGroups;
}) {
    return (
        <>
            <Head title={`Edit ${role.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Edit ${role.name}`}
                    description="Renaming a role keeps its permissions and its users."
                />

                <RoleForm
                    submit={RoleController.update.form(role.id)}
                    permissions={permissions}
                    role={role}
                    submitLabel="Save role"
                />
            </div>
        </>
    );
}

RoleEdit.layout = ({ role }: { role: RoleDetail }) => ({
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: role.name, href: edit(role.id) },
    ],
});
