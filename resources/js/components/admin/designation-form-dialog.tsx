import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import Combobox from '@/components/ui/combobox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DesignationListItem, StatusOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create/edit a designation in a modal — the small sibling of
 * `user-form-dialog.tsx`.
 *
 * `DesignationStoreRequest` and `DesignationUpdateRequest` are what enforce the
 * rules; everything here is feedback, and their errors render in the same
 * `InputError` slots.
 */
export default function DesignationFormDialog({
    submit,
    statuses,
    designation,
    title,
    description,
    submitLabel,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    statuses: StatusOption[];
    designation?: DesignationListItem;
    title: string;
    description: string;
    submitLabel: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    {...submit}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={designation?.name}
                                    required
                                    maxLength={100}
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Senior Merchandiser"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="short_form">
                                    Short form{' '}
                                    <span className="font-normal text-base-content/60">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="short_form"
                                    name="short_form"
                                    defaultValue={designation?.short_form ?? ''}
                                    maxLength={50}
                                    autoComplete="off"
                                    placeholder="SMER"
                                />
                                <InputError message={errors.short_form} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                {/* Two options — below SEARCH_THRESHOLD, so this
                                    renders as a plain listbox with no search
                                    input. See ARCHITECTURE.md §8.5. */}
                                <Combobox
                                    id="status"
                                    name="status"
                                    defaultValue={designation?.status ?? 'A'}
                                    options={statuses}
                                    required
                                    data-test="designation-status"
                                />
                                <p className="text-xs text-base-content/60">
                                    Deactivating hides it from the user form.
                                    Anyone already holding it keeps it.
                                </p>
                                <InputError message={errors.status} />
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
                                    data-test="save-designation"
                                >
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
