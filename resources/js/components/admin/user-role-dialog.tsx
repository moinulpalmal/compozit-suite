import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Replace a user's roles.
 *
 * `roles` arrives already filtered by `UserService::assignableRoleNames()` —
 * `super-admin` is absent unless the signed-in user holds it, so it can be
 * neither granted nor accidentally revoked here. The server refuses either way.
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
                    {...submit}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
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

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-user-roles"
                                >
                                    Save roles
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
