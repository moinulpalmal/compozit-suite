import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCallback, useState } from 'react';
import InputError from '@/components/input-error';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useFormDialog } from '@/hooks/use-form-dialog';
import type { UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Replace a user's roles.
 *
 * `roles` arrives already filtered by `UserService::assignableRoleNames()` —
 * `super-admin` is absent unless the signed-in user holds it, so it can be
 * neither granted nor accidentally revoked here. The server refuses either way.
 *
 * **No "Save & add another"** — it edits one named user's roles, so there is no
 * next record to add (ARCHITECTURE.md §8.10).
 */
export default function UserRoleDialog({
    submit,
    user,
    roles,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    user: UserListItem;
    roles: string[];
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const close = useCallback(() => setOpen(false), []);
    const { formKey, formProps, setIntent } = useFormDialog(close);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>Roles for {user.name}</DialogTitle>
                <DialogDescription>
                    A role is a bundle of permissions. Removing every role
                    leaves this user able to sign in but reach nothing.
                </DialogDescription>

                <Form
                    key={formKey}
                    {...submit}
                    {...formProps}
                    options={{ preserveScroll: true }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                {roles.length === 0 && (
                                    <p className="text-sm text-base-content/60">
                                        No assignable roles exist yet.
                                    </p>
                                )}

                                {roles.map((role) => (
                                    <label
                                        key={role}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            name="roles[]"
                                            value={role}
                                            defaultChecked={user.roles.includes(
                                                role,
                                            )}
                                        />
                                        <span className="font-mono text-xs">
                                            {role}
                                        </span>
                                    </label>
                                ))}
                            </div>

                            <InputError
                                message={errors.roles ?? errors['roles.0']}
                            />

                            <FormDialogFooter
                                processing={processing}
                                onIntent={setIntent}
                                saveTestId="save-user-roles"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
