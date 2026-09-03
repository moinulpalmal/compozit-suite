import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCallback, useState } from 'react';
import InputError from '@/components/input-error';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import Combobox from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormDialog } from '@/hooks/use-form-dialog';
import type { DesignationListItem, StatusOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create/edit a designation in a modal — the small sibling of
 * `user-form-dialog.tsx`, and the reference implementation of the form-modal
 * standard in ARCHITECTURE.md §8.10.
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
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    statuses: StatusOption[];
    designation?: DesignationListItem;
    title: string;
    description: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const close = useCallback(() => setOpen(false), []);
    const { formKey, formProps, setIntent } = useFormDialog(close);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

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
                                    aria-invalid={Boolean(errors.name)}
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
                                    aria-invalid={Boolean(errors.short_form)}
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
                                    aria-invalid={Boolean(errors.status)}
                                    data-test="designation-status"
                                />
                                <p className="text-xs text-base-content/60">
                                    Deactivating hides it from the user form.
                                    Anyone already holding it keeps it.
                                </p>
                                <InputError message={errors.status} />
                            </div>

                            <FormDialogFooter
                                processing={processing}
                                addAnother={designation === undefined}
                                onIntent={setIntent}
                                saveTestId="save-designation"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
