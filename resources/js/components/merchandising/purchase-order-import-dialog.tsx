import { Form, router } from '@inertiajs/react';
import { AlertTriangle, FileUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PurchaseOrderImportController from '@/actions/App/Http/Controllers/Merchandising/PurchaseOrderImportController';
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
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { resolve as resolveRoute } from '@/routes/merchandising/purchase-orders/import';
import type {
    ConflictDecision,
    ConflictDecisionOption,
    ImportableBuyer,
    ImportConflict,
    PendingImport,
} from '@/types';

type Props = {
    buyers: ImportableBuyer[];
    acceptedExtensions: string[];
    maxFileSizeKb: number;
    decisions: ConflictDecisionOption[];
    pendingImport: PendingImport | null;
    /**
     * Controlled by the list page: its Import button opens step one, and its
     * pending-import alert reopens step two. There is no `DialogTrigger` here
     * for that reason.
     */
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Upload a buyer's purchase-order document, and answer for anything it collides with.
 *
 * There is no create form for a purchase order anywhere in this module — an order is
 * read out of the buyer's own document, never typed in. That is why this surface
 * exists at all, and why `import` is its own permission.
 *
 * **Two steps in one dialog.** A document holds up to fifty orders, so an order that
 * matches one already on file cannot be resolved inside the upload request; it is
 * staged on the server and answered here. Step two appears when the page comes back
 * carrying a `pendingImport` — see ARCHITECTURE.md §8.7 for why closing it is safe.
 *
 * **The file input is a plain `<input type="file">`.** ARCHITECTURE.md §8.5's
 * hidden-input contract governs compound controls that replace a native form element;
 * this *is* the native element, so it submits itself. A drag-and-drop zone would be a
 * compound control and would owe that contract — it is deliberately not built here.
 */
export default function PurchaseOrderImportDialog({
    buyers,
    acceptedExtensions,
    maxFileSizeKb,
    decisions,
    pendingImport,
    open,
    onOpenChange,
}: Props) {
    const accept = acceptedExtensions.map((ext) => `.${ext}`).join(',');
    const maxMb = Math.floor(maxFileSizeKb / 1024);

    // Overwrite destroys a stored order, so it is a different power from importing
    // one. Without it the option is absent rather than disabled — a control that
    // cannot be used and does not say why is worse than no control.
    const canOverwrite = useCan('merchandising.purchase-orders.delete');

    const offered = canOverwrite
        ? decisions
        : decisions.filter((decision) => !decision.destructive);

    /*
     * Reopen on the conflict step when an upload comes back with something staged.
     * The ref is seeded with whatever was already pending on first render, so
     * arriving at a page that has an unanswered import does *not* throw a modal at
     * the reader — the list shows an alert and they choose. Only a pending import
     * that is new to this page reopens the dialog.
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
                    <UploadStep
                        buyers={buyers}
                        accept={accept}
                        acceptedExtensions={acceptedExtensions}
                        maxMb={maxMb}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

/** Step one: choose the buyer and the document. */
function UploadStep({
    buyers,
    accept,
    acceptedExtensions,
    maxMb,
}: {
    buyers: ImportableBuyer[];
    accept: string;
    acceptedExtensions: string[];
    maxMb: number;
}) {
    return (
        <>
            <DialogTitle>Import purchase orders</DialogTitle>
            <DialogDescription>
                Upload the buyer&rsquo;s own purchase-order document. Every
                order it contains is read and stored.
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
                    {...PurchaseOrderImportController.store.form()}
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
                                    data-test="import-buyer"
                                />

                                <p className="text-xs text-base-content/60">
                                    Only buyers you have access to are listed.
                                    The parser reads Walmart&rsquo;s import
                                    purchase-order template.
                                </p>

                                <InputError message={errors.buyer_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="file">Document</Label>

                                <input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept={accept}
                                    required
                                    className="file-input-bordered file-input w-full"
                                    data-test="import-file"
                                />

                                <p className="text-xs text-base-content/60">
                                    {acceptedExtensions
                                        .map((ext) => `.${ext}`)
                                        .join(', ')}{' '}
                                    up to {maxMb} MB. A scanned PDF cannot be
                                    read — it has no text to extract.
                                </p>

                                <InputError message={errors.file} />
                            </div>

                            {/* Parsing runs inside the request and shells out to
                                a converter for .doc and .pdf, so a large
                                document is genuinely slow. Saying so is cheaper
                                than a progress bar that lies. */}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="submit-import"
                                >
                                    <FileUp />
                                    {processing
                                        ? 'Reading the document…'
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
 * Step two: one decision per order that collided.
 *
 * Every row starts on **Skip**, so confirming without reading changes nothing that
 * already exists. Cancel submits the same form with every row left on Skip — the
 * discard path and the all-skip path are one code path on the server too.
 */
function ConflictStep({
    pendingImport,
    decisions,
    onDone,
}: {
    pendingImport: PendingImport;
    decisions: ConflictDecisionOption[];
    onDone: () => void;
}) {
    const [chosen, setChosen] = useState<Record<string, ConflictDecision>>({});

    const decisionFor = (poNumber: string) => chosen[poNumber] ?? 'skip';

    return (
        <>
            <DialogTitle>Already on file</DialogTitle>
            <DialogDescription>
                {pendingImport.source_file_name} —{' '}
                {pendingImport.imported_count > 0
                    ? `${pendingImport.imported_count} order${pendingImport.imported_count === 1 ? '' : 's'} imported. `
                    : ''}
                {pendingImport.conflicts.length} already exist
                {pendingImport.conflicts.length === 1 ? 's' : ''} with different
                content. Decide what to do with each.
            </DialogDescription>

            <Form
                {...PurchaseOrderImportController.resolve.form(
                    pendingImport.id,
                )}
                options={{ preserveScroll: true }}
                onSuccess={onDone}
                className="mt-4 space-y-4"
            >
                {({ processing }) => (
                    <>
                        {/* Fifty conflicts must not push the footer off-screen. */}
                        <div className="max-h-96 space-y-3 overflow-y-auto pr-1">
                            {pendingImport.conflicts.map((conflict) => (
                                <ConflictRow
                                    key={conflict.po_number}
                                    conflict={conflict}
                                    decisions={decisions}
                                    chosen={decisionFor(conflict.po_number)}
                                    onChoose={(decision) =>
                                        setChosen((current) => ({
                                            ...current,
                                            [conflict.po_number]: decision,
                                        }))
                                    }
                                />
                            ))}
                        </div>

                        {decisions.every(
                            (decision) => !decision.destructive,
                        ) && (
                            <p className="text-xs text-base-content/60">
                                Replacing an order outright needs the purchase
                                order delete permission, which you do not have.
                            </p>
                        )}

                        <DialogFooter className="gap-2">
                            {/* Not a DialogClose, and not a submit: discarding
                                has to reach the server or the same questions
                                come back on the next visit, and it must not
                                send the radios — clearing them in state would
                                not have re-rendered before the submit fired.
                                Sending no decisions is exactly "skip all",
                                because that is the server's default. */}
                            <Button
                                variant="secondary"
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    router.post(
                                        resolveRoute(pendingImport.id).url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            onSuccess: onDone,
                                        },
                                    )
                                }
                                data-test="cancel-conflicts"
                            >
                                Skip all
                            </Button>

                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="confirm-conflicts"
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

/** One collision: what is held, what arrived, and what to do about it. */
function ConflictRow({
    conflict,
    decisions,
    chosen,
    onChoose,
}: {
    conflict: ImportConflict;
    decisions: ConflictDecisionOption[];
    chosen: ConflictDecision;
    onChoose: (decision: ConflictDecision) => void;
}) {
    const { held, incoming } = conflict;

    return (
        <div className="rounded-box border border-base-300/70 p-3">
            <p className="font-mono text-sm font-semibold">
                {conflict.po_number}
            </p>

            <dl className="mt-2 grid grid-cols-[5rem_1fr] gap-x-3 gap-y-1 text-xs text-base-content/70">
                <dt>On file</dt>
                <dd>
                    rev {held.revision_no}
                    {held.revised_at ? ` · revised ${held.revised_at}` : ''}
                    {held.total_qty !== null
                        ? ` · ${held.total_qty.toLocaleString()} ea`
                        : ''}{' '}
                    · {held.line_item_count} lines
                </dd>

                <dt>Uploaded</dt>
                <dd>
                    {incoming.revised_at
                        ? `revised ${incoming.revised_at}`
                        : 'no revision date'}
                    {incoming.total_qty !== null
                        ? ` · ${incoming.total_qty.toLocaleString()} ea`
                        : ''}{' '}
                    · {incoming.line_item_count} lines
                </dd>
            </dl>

            <div className="mt-3 flex flex-wrap gap-4">
                {decisions.map((decision) => (
                    <label
                        key={decision.value}
                        className="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            type="radio"
                            name={`decisions[${conflict.po_number}]`}
                            value={decision.value}
                            checked={chosen === decision.value}
                            onChange={() => onChoose(decision.value)}
                            className={`radio radio-sm ${decision.destructive ? 'radio-error' : ''}`}
                            data-test={`decision-${conflict.po_number}-${decision.value}`}
                        />
                        <span
                            className={
                                decision.destructive ? 'text-error' : undefined
                            }
                        >
                            {decision.label}
                        </span>
                    </label>
                ))}
            </div>
        </div>
    );
}
