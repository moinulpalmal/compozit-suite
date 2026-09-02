import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
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
import type {
    ColorOption,
    MilestoneOption,
    StatusOption,
    TnaTemplateListItem,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Create/edit a TNA template in a modal — the shape
 * `notification-color-form-dialog.tsx` established.
 *
 * Two things make this larger than its siblings, and both are the register's whole
 * point rather than incidental complexity:
 *
 * - **The band, not a lead time.** `lead_time_from`/`to` are inclusive at both ends.
 *   The reference orders run 263, 264 and 265 days, so a band is what makes one
 *   template serve a season instead of needing a row per integer.
 * - **The ladder is a repeater**, because how many rungs a template wants is the
 *   user's decision. `TnaTemplateStoreRequest` refuses a second open-ended rung and
 *   a colour used twice; everything here is feedback.
 *
 * The milestone inputs are *not* a repeater — they are driven by `milestones`, which
 * the server derives from the enum's schedulable cases. Adding a milestone therefore
 * adds an input here with no change to this file, and `Shipment` never appears
 * because it is read from the purchase order.
 */
export default function TnaTemplateFormDialog({
    submit,
    statuses,
    milestones,
    colorOptions,
    template,
    title,
    description,
    submitLabel,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    statuses: StatusOption[];
    milestones: MilestoneOption[];
    colorOptions: ColorOption[];
    template?: TnaTemplateListItem;
    title: string;
    description: string;
    submitLabel: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-2xl">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    {...submit}
                    /* A repeater with no rows renders no inputs, so `milestones` and
                       `colors` vanish from the payload entirely — and both are `present`
                       in `TnaTemplateValidationRules`, which then rejects a template
                       carrying no colour bands with "The colors field must be present."
                       That is a state the form itself offers ("No bands yet, so dates on
                       this template render uncoloured") and `TnaDateCell` renders, so it
                       has to be submittable.

                       Sending `[]` explicitly rather than relaxing `present` server-side:
                       the children are written as a *set* (ARCHITECTURE.md §5), so the
                       rule is what stops a partial payload silently keeping stale rows.
                       The client owes a complete set, and an empty one is a set. */
                    transform={(data) => ({
                        ...data,
                        milestones: data.milestones ?? [],
                        colors: data.colors ?? [],
                    })}
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
                                    defaultValue={template?.name}
                                    required
                                    maxLength={100}
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Long lead"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="lead_time_from">
                                    Lead time band (days)
                                </Label>

                                <div className="flex items-center gap-2">
                                    <Input
                                        id="lead_time_from"
                                        name="lead_time_from"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        max={65535}
                                        step={1}
                                        defaultValue={
                                            template?.lead_time_from ?? 1
                                        }
                                        required
                                        autoComplete="off"
                                        className="w-32"
                                    />
                                    <span className="text-sm text-base-content/60">
                                        to
                                    </span>
                                    <Input
                                        id="lead_time_to"
                                        name="lead_time_to"
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        max={65535}
                                        step={1}
                                        defaultValue={
                                            template?.lead_time_to ?? 30
                                        }
                                        required
                                        autoComplete="off"
                                        className="w-32"
                                    />
                                </div>

                                <p className="text-xs text-base-content/60">
                                    Both ends included. An order matches when
                                    its lead time — ship date minus BQS date —
                                    falls inside. Active bands may not overlap.
                                </p>
                                <InputError message={errors.lead_time_from} />
                                <InputError message={errors.lead_time_to} />
                            </div>

                            <MilestoneOffsets
                                milestones={milestones}
                                template={template}
                                errors={errors}
                            />

                            <ColorLadder
                                colorOptions={colorOptions}
                                template={template}
                                errors={errors}
                            />

                            <div className="grid gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                <Combobox
                                    id="status"
                                    name="status"
                                    defaultValue={template?.status ?? 'A'}
                                    options={statuses}
                                    required
                                    data-test="tna-template-status"
                                />
                                <p className="text-xs text-base-content/60">
                                    Only active templates match an order.
                                    Deactivating retires a band without deleting
                                    it, and a retired band may overlap its
                                    replacement.
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
                                    data-test="save-tna-template"
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

/**
 * One number input per schedulable milestone, in days after the BQS date.
 *
 * Fixed rows rather than a repeater: the set of milestones is the enum's, not the
 * user's. The index in the field name is the array position the server reads, so
 * the hidden `milestone` input travels beside its offset.
 */
function MilestoneOffsets({
    milestones,
    template,
    errors,
}: {
    milestones: MilestoneOption[];
    template?: TnaTemplateListItem;
    errors: Record<string, string>;
}) {
    const offsetFor = (milestone: string) =>
        template?.milestones.find((row) => row.milestone === milestone)
            ?.offset_days;

    return (
        <div className="grid gap-1.5">
            <Label>Milestone offsets (days after the BQS date)</Label>

            <div className="grid gap-2 rounded-box border border-base-300/70 p-3">
                {milestones.map((milestone, index) => (
                    <div
                        key={milestone.value}
                        className="flex items-center gap-3"
                    >
                        <input
                            type="hidden"
                            name={`milestones[${index}][milestone]`}
                            value={milestone.value}
                        />

                        <Label
                            htmlFor={`milestone-${milestone.value}`}
                            className="flex-1 text-sm font-normal"
                        >
                            {milestone.label}
                        </Label>

                        <Input
                            id={`milestone-${milestone.value}`}
                            name={`milestones[${index}][offset_days]`}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={65535}
                            step={1}
                            defaultValue={offsetFor(milestone.value) ?? 0}
                            required
                            autoComplete="off"
                            className="w-28"
                            data-test={`offset-${milestone.value}`}
                        />
                    </div>
                ))}
            </div>

            <p className="text-xs text-base-content/60">
                Shipment is not here — it is read from the purchase order, and
                is the date the lead time is measured to.
            </p>
            <InputError message={errors.milestones} />
        </div>
    );
}

/**
 * A rung: the days-remaining bound, blank for the single open-ended one.
 *
 * `key` is a stable identity, **not** the array index. The inputs are uncontrolled
 * (`defaultValue`), so keying by position would make React reuse the DOM node of a
 * removed row for the row that slid up into its place — deleting the first band
 * would appear to delete the wrong one and leave the values shifted.
 */
type Rung = {
    key: number;
    /**
     * The id as the server types it — a number, or `null` for a row not yet
     * answered. It was `String(...)`ed here once, which made every band render its
     * placeholder: `ColorOption.value` is an `int`, and `Combobox` matched with
     * `===`. The control now normalises, but the honest type is still a number.
     */
    notification_color_id: number | null;
    max_days_remaining: string;
};

let nextRungKey = 0;

/**
 * The urgency ladder, as an add/remove list.
 *
 * A blank bound means "everything further out" and exactly one rung may be left
 * blank — the server refuses a second, because two catch-alls make the ladder
 * ambiguous at the point it is read. Negative bounds are allowed and meaningful:
 * `-1` is "the date has passed".
 */
function ColorLadder({
    colorOptions,
    template,
    errors,
}: {
    colorOptions: ColorOption[];
    template?: TnaTemplateListItem;
    errors: Record<string, string>;
}) {
    const [rungs, setRungs] = useState<Rung[]>(
        () =>
            template?.colors.map((band) => ({
                key: nextRungKey++,
                notification_color_id: band.notification_color_id,
                max_days_remaining:
                    band.max_days_remaining === null
                        ? ''
                        : String(band.max_days_remaining),
            })) ?? [],
    );

    /* The swatch matters: a colour register picked by name alone is unusable. */
    const options = colorOptions.map((color) => ({
        value: color.value,
        label: color.label,
        hint: color.color_code,
    }));

    return (
        <div className="grid gap-1.5">
            <Label>Colour bands</Label>

            <div className="grid gap-2 rounded-box border border-base-300/70 p-3">
                {rungs.length === 0 && (
                    <p className="text-xs text-base-content/60">
                        No bands yet, so dates on this template render
                        uncoloured.
                    </p>
                )}

                {rungs.map((rung, index) => (
                    <div key={rung.key} className="flex items-end gap-2">
                        <div className="grid flex-1 gap-1">
                            <Combobox
                                name={`colors[${index}][notification_color_id]`}
                                defaultValue={rung.notification_color_id}
                                options={options}
                                placeholder="Choose a colour"
                                required
                                data-test={`band-color-${index}`}
                            />
                            <InputError
                                message={
                                    errors[
                                        `colors.${index}.notification_color_id`
                                    ]
                                }
                            />
                        </div>

                        <div className="grid w-36 gap-1">
                            <Input
                                name={`colors[${index}][max_days_remaining]`}
                                type="number"
                                inputMode="numeric"
                                min={-32768}
                                max={32767}
                                step={1}
                                defaultValue={rung.max_days_remaining}
                                placeholder="Any"
                                autoComplete="off"
                                aria-label="Up to this many days remaining"
                                data-test={`band-days-${index}`}
                            />
                            <InputError
                                message={
                                    errors[`colors.${index}.max_days_remaining`]
                                }
                            />
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove this band"
                            onClick={() =>
                                setRungs((current) =>
                                    current.filter(
                                        (candidate) =>
                                            candidate.key !== rung.key,
                                    ),
                                )
                            }
                            data-test={`remove-band-${index}`}
                        >
                            <Trash2 />
                        </Button>
                    </div>
                ))}

                <div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() =>
                            setRungs((current) => [
                                ...current,
                                {
                                    key: nextRungKey++,
                                    notification_color_id: null,
                                    max_days_remaining: '',
                                },
                            ])
                        }
                        data-test="add-band"
                    >
                        <Plus /> Add band
                    </Button>
                </div>
            </div>

            <p className="text-xs text-base-content/60">
                A date is drawn in the first band whose limit covers the days
                left until it. Leave the days blank for the catch-all — only one
                band may be blank. Use a negative number for overdue.
            </p>
            <InputError message={errors.colors} />
        </div>
    );
}
