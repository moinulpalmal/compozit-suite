import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCallback, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import PasswordPolicyChecklist from '@/components/shared/password-policy-checklist';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useFormDialog } from '@/hooks/use-form-dialog';
import type { UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Set another user's password.
 *
 * No current-password confirmation: this is an administrator acting on someone
 * else's account, gated by `admin.users.reset-password`. Changing your own
 * password still goes through the security settings page, which does ask.
 *
 * **No "Save & add another"** — it acts on one named user, so there is no next
 * record to add (ARCHITECTURE.md §8.10).
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

    // `password` lives out here, above `DialogContent`, so it survives the
    // children unmounting and has to be cleared by hand.
    const close = useCallback(() => {
        setPassword('');
        setOpen(false);
    }, []);

    const { formKey, formProps, setIntent } = useFormDialog(close);

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
                    key={formKey}
                    {...submit}
                    {...formProps}
                    options={{ preserveScroll: true }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="password">New password</Label>

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
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby="password-policy"
                                />

                                <PasswordPolicyChecklist
                                    id="password-policy"
                                    password={password}
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
                                    aria-invalid={Boolean(
                                        errors.password_confirmation,
                                    )}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <FormDialogFooter
                                processing={processing}
                                onIntent={setIntent}
                                saveTestId="save-user-password"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
