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
import type { BuyerListItem, StatusOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create/edit a buyer in a modal — the sibling of `designation-form-dialog.tsx`,
 * and the same three-button contract (ARCHITECTURE.md §8.10).
 *
 * `BuyerStoreRequest` and `BuyerUpdateRequest` enforce the rules; everything here
 * is feedback, and their errors render in the same `InputError` slots.
 */
export default function BuyerFormDialog({
    submit,
    statuses,
    buyer,
    title,
    description,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    statuses: StatusOption[];
    buyer?: BuyerListItem;
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
                                    defaultValue={buyer?.name}
                                    required
                                    maxLength={150}
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Zara"
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="code">
                                    Code{' '}
                                    <span className="font-normal text-base-content/60">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="code"
                                    name="code"
                                    defaultValue={buyer?.code ?? ''}
                                    maxLength={20}
                                    autoComplete="off"
                                    placeholder="ZARA"
                                    aria-invalid={Boolean(errors.code)}
                                />
                                <p className="text-xs text-base-content/60">
                                    The short form used on orders and reports.
                                    Searched by prefix, so it stays quick to
                                    look up.
                                </p>
                                <InputError message={errors.code} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                {/* Two options — below SEARCH_THRESHOLD, so this
                                    renders as a plain listbox with no search
                                    input. See ARCHITECTURE.md §8.5. */}
                                <Combobox
                                    id="status"
                                    name="status"
                                    defaultValue={buyer?.status ?? 'A'}
                                    options={statuses}
                                    required
                                    aria-invalid={Boolean(errors.status)}
                                    data-test="buyer-status"
                                />
                                <p className="text-xs text-base-content/60">
                                    Deactivating removes it from the access
                                    picker. Existing grants and orders are
                                    untouched.
                                </p>
                                <InputError message={errors.status} />
                            </div>

                            <FormDialogFooter
                                processing={processing}
                                addAnother={buyer === undefined}
                                onIntent={setIntent}
                                saveTestId="save-buyer"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
