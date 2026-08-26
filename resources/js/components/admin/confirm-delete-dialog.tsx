import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
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

/**
 * Icon button that asks before submitting a destructive Wayfinder form.
 */
export default function ConfirmDeleteDialog({
    submit,
    title,
    description,
    disabled = false,
    testId,
}: {
    submit: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    disabled?: boolean;
    testId?: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    disabled={disabled}
                    aria-label={title}
                    data-test={testId}
                >
                    <Trash2 className="text-error" />
                </Button>
            </DialogTrigger>

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
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                                data-test={testId && `confirm-${testId}`}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
