import { Form } from '@inertiajs/react';
import { AlertTriangle, FileUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import BqsImportController from '@/actions/App/Http/Controllers/Merchandising/BqsImportController';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import type {
    BqsConflictDecision,
    BqsConflictDecisionOption,
    BqsPendingImport,
    ImportableBuyer,
} from '@/types';

type Props = {
    buyers: ImportableBuyer[];
    maxFileSizeKb: number;
    decisions: BqsConflictDecisionOption[];
    pendingImport: BqsPendingImport | null;
    /**
     * Controlled by the list page: its Import button opens step one, and its
     * pending-import alert reopens step two. There is no `DialogTrigger` here
     * for that reason.
     */
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Upload a buyer's BQS workbook, and answer if it overlaps one already held.
 *
 * There is no create form for a BQS anywhere in this module — it is the buyer's own
 * workbook, read rather than typed. That is why this surface exists at all, and why
 * `import` is its own permission.
 *
 * **The form asks for three things and two are not in the file.** The buyer is chosen
 * so an import cannot land somewhere the uploader cannot see it (ARCHITECTURE.md
 * §9.2), and the BQS date is chosen because the workbook carries no date of any kind.
 *
 * **Two steps in one dialog, but step two asks one question.** Unlike the
 * purchase-order dialog, which decides per order, a workbook *is* one BQS: it is the
 * overlap between its row keys and a held revision's that identifies it, so there is
 * one decision no matter how many rows. A 200-row BQS would otherwise produce a
 * 200-decision form.
 *
 * **The file input is a plain `<input type="file">`.** §8.5's hidden-input contract
 * governs compound controls that replace a native form element; this *is* the native
 * element, so it submits itself.
 */
export default function BqsImportDialog({
    buyers,
    maxFileSizeKb,
    decisions,
    pendingImport,
    open,
    onOpenChange,
}: Props) {
    const maxMb = Math.floor(maxFileSizeKb / 1024);

    // Overwrite destroys a stored revision and its rows, so it is a different power
    // from importing one. Without it the option is absent rather than disabled — a
    // control that cannot be used and does not say why is worse than no control.
    const canOverwrite = useCan('merchandising.bqs.delete');

    const offered = canOverwrite
        ? decisions
        : decisions.filter((decision) => !decision.destructive);

    /*
     * Reopen on the conflict step when an upload comes back with something staged.
     * The ref is seeded with whatever was already pending on first render, so
     * arriving at a page that has an unanswered import does *not* throw a modal at
     * the reader — the list shows an alert and they choose.
     */
    const lastSeen = useRef<number | null>(pendingImport?.id ?? null);

    useEffect(() => {
        const id = pendingImport?.id ?? null;

        if (id !== null && id !== lastSeen.current) {
            onOpenChange(true);
        }

        lastSeen.current = id;
    }, [pendingImport?.id, onOpenChange]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                {pendingImport ? (
                    <ConflictStep
                        pendingImport={pendingImport}
                        decisions={offered}
                        onDone={() => onOpenChange(false)}
                    />
                ) : (
                    <UploadStep buyers={buyers} maxMb={maxMb} />
                )}
            </DialogContent>
        </Dialog>
    );
}

/** Step one: the buyer, the BQS date, and the workbook. */
function UploadStep({
    buyers,
    maxMb,
}: {
    buyers: ImportableBuyer[];
    maxMb: number;
}) {
    return (
        <>
            <DialogTitle>Import a BQS</DialogTitle>
            <DialogDescription>
                Upload the buyer&rsquo;s own BQS workbook. Every style and
                colourway in it is read and stored.
            </DialogDescription>

            {buyers.length === 0 ? (
                /* Zero buyers is a legitimate state — a new hire pending
                   assignment (ARCHITECTURE.md §9.2). Say so, rather than
                   showing a form that cannot be submitted. */
                <div
                    role="status"
                    className="mt-4 alert alert-soft alert-warning"
                >
                    <AlertTriangle className="size-5" />
                    <span>
                        You do not have access to any active buyer yet, so there
                        is nothing to import for. An administrator grants buyer
                        access from the users screen.
                    </span>
                </div>
            ) : (
                <Form
                    {...BqsImportController.store.form()}
                    options={{ preserveScroll: true }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="buyer_id">Buyer</Label>

                                <Combobox
                                    id="buyer_id"
                                    name="buyer_id"
                                    options={buyers}
                                    placeholder="Choose a buyer"
                                    required
                                    data-test="bqs-import-buyer"
                                />

                                <p className="text-xs text-base-content/60">
                                    Only buyers you have access to are listed.
                                </p>

                                <InputError message={errors.buyer_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="bqs_date">BQS date</Label>

                                <Input
                                    id="bqs_date"
                                    name="bqs_date"
                                    type="date"
                                    required
                                    data-test="bqs-import-date"
                                />

                                {/* The workbook has no document date, no
                                    revision date and a blank Quote ID, so this
                                    cannot be read out of it — and a file's own
                                    timestamp is the date it was last copied. */}
                                <p className="text-xs text-base-content/60">
                                    The workbook carries no date, so enter the
                                    one this BQS was issued for.
                                </p>

                                <InputError message={errors.bqs_date} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="file">Workbook</Label>

                                <input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,.xls"
                                    required
                                    className="file-input-bordered file-input w-full"
                                    data-test="bqs-import-file"
                                />

                                <p className="text-xs text-base-content/60">
                                    .xlsx or .xls up to {maxMb} MB. The header
                                    must be the buyer&rsquo;s two-row BQS
                                    layout; month and size columns may be
                                    anything.
                                </p>

                                <InputError message={errors.file} />
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
                                    data-test="submit-bqs-import"
                                >
                                    <FileUp />
                                    {processing
                                        ? 'Reading the workbook…'
                                        : 'Import'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}

/**
 * Step two: the one decision this workbook needs.
 *
 * It starts on **Skip**, so confirming without reading changes nothing that already
 * exists. Cancel submits the same form still on Skip — the discard path and the
 * skip path are one code path on the server too.
 */
function ConflictStep({
    pendingImport,
    decisions,
    onDone,
}: {
    pendingImport: BqsPendingImport;
    decisions: BqsConflictDecisionOption[];
    onDone: () => void;
}) {
    const [chosen, setChosen] = useState<BqsConflictDecision>('skip');

    return (
        <>
            <DialogTitle>Already on file</DialogTitle>
            <DialogDescription>
                {pendingImport.source_file_name} overlaps{' '}
                <strong>{pendingImport.collides_with_title}</strong> (revision{' '}
                {pendingImport.collides_with_revision}) —{' '}
                {pendingImport.overlapping_rows} of its{' '}
                {pendingImport.row_count} row
                {pendingImport.row_count === 1 ? '' : 's'} already exist
                {pendingImport.overlapping_rows === 1 ? 's' : ''} there. Decide
                what to do.
            </DialogDescription>

            <Form
                {...BqsImportController.resolve.form(pendingImport.id)}
                options={{ preserveScroll: true }}
                onSuccess={onDone}
                className="mt-4 space-y-4"
            >
                {({ processing }) => (
                    <>
                        <div
                            className="space-y-2"
                            role="radiogroup"
                            aria-label="What to do with this BQS"
                        >
                            {decisions.map((decision) => (
                                <label
                                    key={decision.value}
                                    className={`flex cursor-pointer items-start gap-3 rounded-box border p-3 ${
                                        chosen === decision.value
                                            ? 'border-primary bg-primary/5'
                                            : 'border-base-300/70'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="decision"
                                        value={decision.value}
                                        checked={chosen === decision.value}
                                        onChange={() =>
                                            setChosen(decision.value)
                                        }
                                        className={`radio mt-0.5 radio-sm ${
                                            decision.destructive
                                                ? 'radio-error'
                                                : ''
                                        }`}
                                        data-test={`bqs-decision-${decision.value}`}
                                    />

                                    <span>
                                        <span className="block font-medium">
                                            {decision.label}
                                        </span>
                                        <span className="block text-xs text-base-content/60">
                                            {DECISION_HELP[decision.value]}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>

                        {pendingImport.warnings.length > 0 && (
                            <div
                                role="status"
                                className="alert alert-soft alert-warning"
                            >
                                <AlertTriangle className="size-5" />
                                <span className="text-xs">
                                    {pendingImport.warnings.length} warning
                                    {pendingImport.warnings.length === 1
                                        ? ''
                                        : 's'}{' '}
                                    were raised reading this workbook. They stay
                                    on the import either way.
                                </span>
                            </div>
                        )}

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                disabled={processing}
                                variant={
                                    chosen === 'overwrite'
                                        ? 'destructive'
                                        : 'default'
                                }
                                data-test="submit-bqs-decision"
                            >
                                {processing ? 'Applying…' : 'Confirm'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>
        </>
    );
}

/** What each decision actually does, in the reader's terms. */
const DECISION_HELP: Record<BqsConflictDecision, string> = {
    skip: 'Keep what is on file. The workbook is recorded but nothing changes.',
    revise: 'Store this as the next revision. The current one is kept as history.',
    overwrite:
        'Replace the current revision and delete its rows. This cannot be undone.',
};
