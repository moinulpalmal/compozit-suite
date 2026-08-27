import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { PasswordStrength } from '@/components/admin/user-form-dialog';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Set another user's password.
 *
 * No current-password confirmation: this is an administrator acting on someone
 * else's account, gated by `admin.users.reset-password`. Changing your own
 * password still goes through the security settings page, which does ask.
 */
export default function UserPasswordDialog({
    submit,
    user,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    user: UserListItem;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>Set a password for {user.name}</DialogTitle>
                <DialogDescription>
                    The user is not notified. Hand the new password over
                    yourself, and ask them to change it.
                </DialogDescription>

                <Form
                    {...submit}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setPassword('');
                        setOpen(false);
                    }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <div className="flex items-baseline justify-between gap-2">
                                    <Label htmlFor="password">
                                        New password
                                    </Label>
                                    <PasswordStrength password={password} />
                                </div>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    value={password}
                                    onChange={(event) =>
                                        setPassword(event.target.value)
                                    }
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-user-password"
                                >
                                    Set password
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
