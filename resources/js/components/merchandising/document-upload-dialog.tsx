import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import DocumentUploadController from '@/actions/App/Http/Controllers/Merchandising/DocumentUploadController';
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
import type { ImportableBuyer, StatusOption } from '@/types';

type Props = {
    buyers: ImportableBuyer[];
    documentTypes: StatusOption[];
    maxFilesPerBatch: number;
    allowedExtensions: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Upload a batch of files to the document library.
 *
 * **Nothing uploaded here is read.** The type is a label the uploader picks so the
 * file can be found again — a batch typed "BQS" is a stored document, not an imported
 * BQS, and it writes no buy-plan rows. The import dialogs on the BQS and purchase-order
 * lists are the surfaces that parse; the copy below says so, because that distinction
 * is the one thing a user can get wrong here.
 *
 * **The buyer is optional, unlike both import dialogs.** A size chart or a TNA formula
 * often concerns no single buyer, and leaving it blank means everyone with access to
 * the library sees it (ARCHITECTURE.md §9.2). Choosing one narrows it to the people
 * who can see that buyer — so an empty buyer list is not the blocker it is on an
 * import form, and this dialog does not refuse to render for it.
 *
 * **The file input is `multiple` and its name carries the brackets exactly once.**
 * `name="files[]"` is what a `files.*` rule validates; a `Combobox multiple` once
 * shipped emitting `buyers[][]`, which no submit survived, and the browser suite
 * exists because a feature test cannot see what the form put on the wire.
 */
export default function DocumentUploadDialog({
    buyers,
    documentTypes,
    maxFilesPerBatch,
    allowedExtensions,
    open,
    onOpenChange,
}: Props) {
    const accept = allowedExtensions.map((ext) => `.${ext}`).join(',');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogTitle>Upload documents</DialogTitle>
                <DialogDescription>
                    Files are stored as they arrive. Nothing is read out of them
                    &mdash; to import a BQS or a purchase order, use the Import
                    button on those screens instead.
                </DialogDescription>

                <Form
                    {...DocumentUploadController.store.form()}
                    options={{ preserveScroll: true }}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="file_type">
                                    What are these?
                                </Label>

                                <Combobox
                                    id="file_type"
                                    name="file_type"
                                    options={documentTypes}
                                    placeholder="Choose a document type"
                                    required
                                    data-test="document-type"
                                />

                                <p className="text-xs text-base-content/60">
                                    A label for finding them again, not an
                                    instruction to read them.
                                </p>

                                <InputError message={errors.file_type} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="buyer_id">
                                    Buyer{' '}
                                    <span className="text-base-content/50">
                                        (optional)
                                    </span>
                                </Label>

                                <Combobox
                                    id="buyer_id"
                                    name="buyer_id"
                                    options={buyers}
                                    placeholder="No particular buyer"
                                    data-test="document-buyer"
                                />

                                {/* Says what blank *means*, because "optional"
                                    alone reads as "unimportant" and this one
                                    decides who can see the files. */}
                                <p className="text-xs text-base-content/60">
                                    Leave blank if these concern no particular
                                    buyer &mdash; everyone with access to the
                                    library will see them. Choosing a buyer
                                    limits them to people who can see that
                                    buyer.
                                </p>

                                <InputError message={errors.buyer_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="title">
                                    Title{' '}
                                    <span className="text-base-content/50">
                                        (optional)
                                    </span>
                                </Label>

                                <Input
                                    id="title"
                                    name="title"
                                    type="text"
                                    maxLength={255}
                                    placeholder="What this batch is"
                                    data-test="document-title"
                                />

                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="note">
                                    Note{' '}
                                    <span className="text-base-content/50">
                                        (optional)
                                    </span>
                                </Label>

                                <textarea
                                    id="note"
                                    name="note"
                                    rows={2}
                                    maxLength={2000}
                                    className="textarea-bordered textarea w-full"
                                    placeholder="Who sent them, what they are for"
                                    data-test="document-note"
                                />

                                <InputError message={errors.note} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="files">Files</Label>

                                <input
                                    id="files"
                                    name="files[]"
                                    type="file"
                                    multiple
                                    accept={accept}
                                    required
                                    className="file-input-bordered file-input w-full"
                                    data-test="document-files"
                                />

                                {/* The cap is PHP's `max_file_uploads`: file
                                    21 is dropped from the request before any
                                    code runs, so saying so here is the only
                                    warning a user can get. */}
                                <p className="text-xs text-base-content/60">
                                    Up to {maxFilesPerBatch} files at a time
                                    &mdash; send more as a second batch. There
                                    is no size limit beyond what the server
                                    accepts.
                                </p>

                                <InputError
                                    message={errors.files ?? errors['files.0']}
                                />
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
                                    data-test="submit-document-upload"
                                >
                                    <Upload />
                                    {processing ? 'Uploading…' : 'Upload'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
