import { Form } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useCallback } from 'react';
import DocumentFileController from '@/actions/App/Http/Controllers/Merchandising/DocumentFileController';
import InputError from '@/components/input-error';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useFormDialog } from '@/hooks/use-form-dialog';
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
 *
 * **This dialog used to stay open after a successful replace**, because its `open`
 * derives from `file !== null` and nothing cleared it — so the panel sat there
 * offering to replace the file it had just replaced. It now closes through the
 * standard in ARCHITECTURE.md §8.10. No "Save & add another": a replacement acts
 * on one named file, and there is no next one to add.
 */
export default function DocumentReplaceDialog({
    uploadId,
    file,
    allowedExtensions,
    onOpenChange,
}: Props) {
    const accept = allowedExtensions.map((ext) => `.${ext}`).join(',');
    const close = useCallback(() => onOpenChange(false), [onOpenChange]);
    const { formKey, formProps, setIntent } = useFormDialog(close);

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
                        key={formKey}
                        {...DocumentFileController.update.form({
                            documentUpload: uploadId,
                            documentFile: file.id,
                        })}
                        {...formProps}
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
                                        autoFocus
                                        className="file-input-bordered file-input w-full"
                                        aria-invalid={Boolean(errors.file)}
                                        data-test="document-replacement-file"
                                    />

                                    <InputError message={errors.file} />
                                </div>

                                {/* Destructive styling survives the standard
                                    label: the panel's title and description say
                                    what is destroyed, and the red button is what
                                    stops it reading as an ordinary save. */}
                                <FormDialogFooter
                                    processing={processing}
                                    onIntent={setIntent}
                                    saveVariant="destructive"
                                    saveIcon={<RefreshCw />}
                                    saveTestId="submit-document-replacement"
                                />
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
