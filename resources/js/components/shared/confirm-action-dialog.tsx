import { Form } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
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
import type { RouteFormDefinition } from '@/wayfinder';

type ButtonVariant = ComponentProps<typeof Button>['variant'];

/**
 * Asks before submitting a Wayfinder form, then submits it.
 *
 * The trigger is whatever you pass as `children` — an icon button in a table
 * row, a full-width button in a modal. `ConfirmDeleteDialog` is the destructive
 * preset over this.
 */
export default function ConfirmActionDialog({
    submit,
    title,
    description,
    confirmLabel,
    confirmVariant = 'destructive',
    disabled = false,
    testId,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    confirmLabel: string;
    confirmVariant?: ButtonVariant;
    disabled?: boolean;
    testId?: string;
    children: ReactNode;
}) {
    // A disabled trigger still renders — it just never opens the dialog.
    if (disabled) {
        return <>{children}</>;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form {...submit} options={{ preserveScroll: true }}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                variant={confirmVariant}
                                type="submit"
                                disabled={processing}
                                data-test={testId && `confirm-${testId}`}
                            >
                                {confirmLabel}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
