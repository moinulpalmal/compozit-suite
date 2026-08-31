import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/permissions';
import type { PermissionDetail } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The create/edit form for a permission. `submit` takes a Wayfinder `.form()`
 * result; roles are attached straight from here so a new permission is usable
 * without a second trip through the role screen.
 */
export default function PermissionForm({
    submit,
    roles,
    permission,
    submitLabel,
}: {
    submit: RouteFormDefinition<'post'>;
    roles: string[];
    permission?: PermissionDetail;
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
                            defaultValue={permission?.name}
                            required
                            autoFocus
                            placeholder="merchandising.tech-packs.update"
                            className="font-mono"
                        />

                        <p className="text-xs text-base-content/60">
                            Three kebab-case segments:{' '}
                            <span className="font-mono">
                                module.resource.action
                            </span>
                            .
                        </p>

                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label>Roles</Label>

                        {roles.length === 0 ? (
                            <p className="text-sm text-base-content/60">
                                No assignable roles yet.
                            </p>
                        ) : (
                            <div className="grid gap-1.5 sm:grid-cols-2 md:grid-cols-3">
                                {roles.map((role) => (
                                    <label
                                        key={role}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            name="roles[]"
                                            value={role}
                                            defaultChecked={permission?.roles.includes(
                                                role,
                                            )}
                                        />
                                        {role}
                                    </label>
                                ))}
                            </div>
                        )}

                        <p className="text-xs text-base-content/60">
                            The super-admin role is omitted — it bypasses every
                            permission check already.
                        </p>

                        <InputError message={errors.roles} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={processing}
                            data-test="save-permission"
                        >
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
