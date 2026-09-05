import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useCallback, useState } from 'react';
import InputError from '@/components/input-error';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import ColorInput from '@/components/ui/color-input';
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
import type { NotificationColorListItem, StatusOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create/edit a notification colour in a modal — the shape
 * `designation-form-dialog.tsx` established, including its three-button footer
 * (ARCHITECTURE.md §8.10).
 *
 * `NotificationColorStoreRequest` and `NotificationColorUpdateRequest` are what
 * enforce the rules; everything here is feedback, and their errors render in the
 * same `InputError` slots.
 */
export default function NotificationColorFormDialog({
    submit,
    statuses,
    notificationColor,
    title,
    description,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    statuses: StatusOption[];
    notificationColor?: NotificationColorListItem;
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
                                    defaultValue={notificationColor?.name}
                                    required
                                    maxLength={100}
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Urgent"
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="color_code">Colour</Label>
                                {/* Only the hex field carries `name` — see the
                                    component's docblock and ARCHITECTURE.md §8.5. */}
                                <ColorInput
                                    id="color_code"
                                    name="color_code"
                                    defaultValue={
                                        notificationColor?.color_code ??
                                        '#3B82F6'
                                    }
                                    required
                                    invalid={Boolean(errors.color_code)}
                                />
                                <p className="text-xs text-base-content/60">
                                    Stored as typed. The theme does not adjust
                                    it, so check it reads well in both light and
                                    dark.
                                </p>
                                <InputError message={errors.color_code} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="retention_days">
                                    Retention (days)
                                </Label>
                                <Input
                                    id="retention_days"
                                    name="retention_days"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={3650}
                                    step={1}
                                    defaultValue={
                                        notificationColor?.retention_days ?? 30
                                    }
                                    required
                                    autoComplete="off"
                                    aria-invalid={Boolean(
                                        errors.retention_days,
                                    )}
                                />
                                <p className="text-xs text-base-content/60">
                                    How long a notification in this colour is
                                    kept before it ages out.
                                </p>
                                <InputError message={errors.retention_days} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                {/* Two options — below SEARCH_THRESHOLD, so this
                                    renders as a plain listbox with no search
                                    input. See ARCHITECTURE.md §8.5. */}
                                <Combobox
                                    id="status"
                                    name="status"
                                    defaultValue={
                                        notificationColor?.status ?? 'A'
                                    }
                                    options={statuses}
                                    required
                                    aria-invalid={Boolean(errors.status)}
                                    data-test="notification-color-status"
                                />
                                <p className="text-xs text-base-content/60">
                                    Deactivating hides it from the pickers.
                                    Anything already using it keeps it.
                                </p>
                                <InputError message={errors.status} />
                            </div>

                            <FormDialogFooter
                                processing={processing}
                                addAnother={notificationColor === undefined}
                                onIntent={setIntent}
                                saveTestId="save-notification-color"
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
