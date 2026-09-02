import { Form } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import DocumentFileController from '@/actions/App/Http/Controllers/Merchandising/DocumentFileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { DocumentFileItem } from '@/types';

type Props = {
    uploadId: number;
    file: DocumentFileItem | null;
    allowedExtensions: string[];
    onOpenChange: (open: boolean) => void;
};

/**
 * Put a new file in an existing one's place.
 *
 * **The file it replaces is destroyed and there is no version history**, which is why
 * the copy says so and why the action needs `merchandising.documents.delete` on top of
 * `update` — `DocumentFileReplaceRequest` enforces that, and the file list hides this
 * control entirely from anyone without both. It is the same split `BqsResolveRequest`
 * makes for overwriting a BQS revision.
 *
 * The row keeps its id and its place in the batch; only the bytes and the name change.
 *
 * `POST`, not `PUT`: a browser form cannot send a multipart body with `PUT`.
 */
export default function DocumentReplaceDialog({
    uploadId,
    file,
    allowedExtensions,
    onOpenChange,
}: Props) {
    const accept = allowedExtensions.map((ext) => `.${ext}`).join(',');

    return (
        <Dialog open={file !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogTitle>Replace this file</DialogTitle>

                <DialogDescription>
                    {file?.original_name} will be deleted and the new file takes
                    its place. There is no version history, so this cannot be
                    undone.
                </DialogDescription>

                {file && (
                    <Form
                        {...DocumentFileController.update.form({
                            documentUpload: uploadId,
                            documentFile: file.id,
                        })}
                        options={{ preserveScroll: true }}
                        className="mt-4 space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="replacement">
                                        New file
                                    </Label>

                                    <input
                                        id="replacement"
                                        name="file"
                                        type="file"
                                        accept={accept}
                                        required
                                        className="file-input-bordered file-input w-full"
                                        data-test="document-replacement-file"
                                    />

                                    <InputError message={errors.file} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="submit-document-replacement"
                                    >
                                        <RefreshCw />
                                        {processing
                                            ? 'Replacing…'
                                            : 'Replace file'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
