import { Form, Head, Link } from '@inertiajs/react';
import { FileUp, Info } from 'lucide-react';
import PurchaseOrderImportController from '@/actions/App/Http/Controllers/Merchandising/PurchaseOrderImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import Combobox from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/merchandising/purchase-orders';
import { create } from '@/routes/merchandising/purchase-orders/import';
import type { ImportableBuyer } from '@/types';

type Props = {
    buyers: ImportableBuyer[];
    acceptedExtensions: string[];
    maxFileSizeKb: number;
};

/**
 * Upload a buyer's purchase-order document and import every order in it.
 *
 * There is no create form for a purchase order anywhere in this module — an
 * order is read out of the buyer's own document, never typed in. That is why
 * this surface exists at all, and why `import` is its own permission.
 *
 * **The file input is a plain `<input type="file">`.** ARCHITECTURE.md §8.5's
 * hidden-input contract governs compound controls that replace a native form
 * element; this *is* the native element, so it submits itself. A drag-and-drop
 * zone would be a compound control and would owe that contract — it is
 * deliberately not built here.
 */
export default function PurchaseOrderImport({
    buyers,
    acceptedExtensions,
    maxFileSizeKb,
}: Props) {
    const accept = acceptedExtensions.map((ext) => `.${ext}`).join(',');
    const maxMb = Math.floor(maxFileSizeKb / 1024);

    return (
        <>
            <Head title="Import purchase orders" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Import purchase orders"
                    description="Upload the buyer's own purchase-order document. Every order it contains is read and stored."
                />

                {buyers.length === 0 ? (
                    /* Zero buyers is a legitimate state — a new hire pending
                       assignment (ARCHITECTURE.md §9.2). Say so, rather than
                       showing a form that cannot be submitted. */
                    <div
                        role="status"
                        className="alert max-w-xl alert-soft alert-warning"
                    >
                        <Info className="size-5" />
                        <span>
                            You do not have access to any active buyer yet, so
                            there is nothing to import for. An administrator
                            grants buyer access from the users screen.
                        </span>
                    </div>
                ) : (
                    <Form
                        {...PurchaseOrderImportController.store.form()}
                        className="max-w-xl space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
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
                                        Only buyers you have access to are
                                        listed. The parser reads Walmart's
                                        import purchase-order template.
                                    </p>

                                    <InputError message={errors.buyer_id} />
                                </div>

                                <div className="grid gap-2">
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
                                        up to {maxMb} MB. A scanned PDF cannot
                                        be read — it has no text to extract.
                                    </p>

                                    <InputError message={errors.file} />
                                </div>

                                {/* Parsing runs inside the request and shells
                                    out to a converter for .doc and .pdf, so a
                                    large document is genuinely slow. Saying so
                                    is cheaper than a progress bar that lies. */}
                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="submit-import"
                                    >
                                        <FileUp />
                                        {processing
                                            ? 'Reading the document…'
                                            : 'Import'}
                                    </Button>

                                    <Button variant="ghost" asChild>
                                        <Link href={index()}>Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

PurchaseOrderImport.layout = {
    breadcrumbs: [
        { title: 'Purchase orders', href: index() },
        { title: 'Import', href: create() },
    ],
};
