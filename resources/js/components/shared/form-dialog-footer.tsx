import type { ReactNode } from 'react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DialogClose,
    DialogFooter,
    useDialogBusy,
} from '@/components/ui/dialog';
import type { SaveIntent } from '@/hooks/use-form-dialog';

/**
 * The footer every insert/update modal in this application wears —
 * ARCHITECTURE.md §8.10. Pair it with `hooks/use-form-dialog.ts`, which owns what
 * each button *means*; this file only renders them.
 *
 * ```tsx
 * <FormDialogFooter
 *     processing={processing}
 *     addAnother={designation === undefined}
 *     onIntent={setIntent}
 *     saveTestId="save-designation"
 * />
 * ```
 *
 * **Order is Cancel · Save & add another · Save & close, in the DOM as well as on
 * screen**, and the two agree on purpose. Implicit submission — Enter in a text
 * field — activates the *first* submit button in tree order, so Enter means "save
 * and keep going" on a create modal and "save and finish" on an edit one, where
 * the middle button is absent. Reordering the DOM to change that would leave tab
 * order disagreeing with what the eye sees, which is a worse trade than the one it
 * buys.
 *
 * **"Save & add another" is create-only.** An edit modal posts to that record's
 * update route, so a second submit would re-save the same row rather than create a
 * new one. Pass `addAnother` from whether the dialog was given a record.
 *
 * **Every control here is disabled while the save is in flight**, the exits
 * included, and the effect below mirrors that into the panel so `DialogContent`
 * disables its X too. Between the request leaving and the answer arriving there is
 * nothing useful a second click can do, and closing the panel over an unanswered
 * save is how a user ends up not knowing whether the record landed.
 */
export default function FormDialogFooter({
    processing,
    addAnother = false,
    onIntent,
    saveTestId,
    addAnotherTestId = `${saveTestId}-another`,
    saveVariant = 'default',
    saveIcon,
}: {
    /** The `<Form>` slot's `processing`. */
    processing: boolean;
    /** Render "Save & add another" — create modals only. */
    addAnother?: boolean;
    /** `setIntent` from `useFormDialog`. */
    onIntent: (intent: SaveIntent) => void;
    /** Kept from each dialog's original save button, so selectors do not churn. */
    saveTestId: string;
    addAnotherTestId?: string;
    /** For a save that is also destructive — the document replace dialog. */
    saveVariant?: 'default' | 'destructive';
    saveIcon?: ReactNode;
}) {
    const setBusy = useDialogBusy();

    useEffect(() => {
        setBusy(processing);

        // A dialog that closes mid-flight would otherwise leave the panel stuck
        // busy, since this whole subtree unmounts with the children.
        return () => setBusy(false);
    }, [processing, setBusy]);

    return (
        <DialogFooter className="gap-2">
            <DialogClose asChild>
                <Button variant="secondary" type="button" disabled={processing}>
                    Cancel
                </Button>
            </DialogClose>

            {addAnother && (
                <Button
                    type="submit"
                    variant="secondary"
                    disabled={processing}
                    onClick={() => onIntent('add-another')}
                    data-test={addAnotherTestId}
                >
                    Save &amp; add another
                </Button>
            )}

            <Button
                type="submit"
                variant={saveVariant}
                disabled={processing}
                onClick={() => onIntent('close')}
                data-test={saveTestId}
            >
                {saveIcon}
                Save &amp; close
            </Button>
        </DialogFooter>
    );
}
