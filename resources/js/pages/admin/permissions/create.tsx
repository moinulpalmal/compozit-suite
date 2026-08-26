import { Head } from '@inertiajs/react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import PermissionForm from '@/components/admin/permission-form';
import Heading from '@/components/heading';
import { create, index } from '@/routes/admin/permissions';

export default function PermissionCreate({ roles }: { roles: string[] }) {
    return (
        <>
            <Head title="New permission" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New permission"
                    description="Add an ability, then hand it to the roles that need it."
                />

                <PermissionForm
                    submit={PermissionController.store.form()}
                    roles={roles}
                    submitLabel="Create permission"
                />
            </div>
        </>
    );
}

PermissionCreate.layout = {
    breadcrumbs: [
        { title: 'Permissions', href: index() },
        { title: 'New permission', href: create() },
    ],
};
