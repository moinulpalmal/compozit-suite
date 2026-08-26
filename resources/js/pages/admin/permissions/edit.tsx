import { Head } from '@inertiajs/react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import PermissionForm from '@/components/admin/permission-form';
import Heading from '@/components/heading';
import { edit, index } from '@/routes/admin/permissions';
import type { PermissionDetail } from '@/types';

export default function PermissionEdit({
    permission,
    roles,
}: {
    permission: PermissionDetail;
    roles: string[];
}) {
    return (
        <>
            <Head title={`Edit ${permission.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Edit ${permission.name}`}
                    description="Renaming a permission does not update the code that checks it — search for the old name first."
                />

                <PermissionForm
                    submit={PermissionController.update.form(permission.id)}
                    roles={roles}
                    permission={permission}
                    submitLabel="Save permission"
                />
            </div>
        </>
    );
}

PermissionEdit.layout = ({ permission }: { permission: PermissionDetail }) => ({
    breadcrumbs: [
        { title: 'Permissions', href: index() },
        { title: permission.name, href: edit(permission.id) },
    ],
});
