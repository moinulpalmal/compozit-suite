import { Form, Link } from '@inertiajs/react';
import PermissionPicker from '@/components/admin/permission-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/roles';
import type { PermissionGroups, RoleDetail } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The create/edit form for a role. `submit` takes a Wayfinder `.form()` result.
 */
export default function RoleForm({
    submit,
    permissions,
    role,
    submitLabel,
}: {
    submit: RouteFormDefinition<'post'>;
    permissions: PermissionGroups;
    role?: RoleDetail;
    submitLabel: string;
}) {
    return (
        <Form {...submit} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid max-w-md gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            name="name"
                            defaultValue={role?.name}
                            required
                            autoFocus
                            placeholder="production-manager"
                        />

                        <p className="text-xs text-base-content/60">
                            Lowercase kebab-case. Roles are data — grant
                            abilities through permissions, never by checking the
                            role name in code.
                        </p>

                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label>Permissions</Label>

                        <PermissionPicker
                            groups={permissions}
                            defaultSelected={role?.permissions ?? []}
                        />

                        <InputError message={errors.permissions} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing} data-test="save-role">
                            {submitLabel}
                        </Button>

                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
