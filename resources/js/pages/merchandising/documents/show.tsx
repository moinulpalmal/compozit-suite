import { Head } from '@inertiajs/react';
import { Download, Eye, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import BackLink from '@/components/merchandising/back-link';
import DocumentPreviewDialog, {
    formatBytes,
} from '@/components/merchandising/document-preview-dialog';
import DocumentReplaceDialog from '@/components/merchandising/document-replace-dialog';
import ConfirmDeleteDialog from '@/components/shared/confirm-delete-dialog';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { index, show } from '@/routes/merchandising/documents';
import { destroy as destroyBatch } from '@/routes/merchandising/documents';
import {
    destroy as destroyFile,
    download,
} from '@/routes/merchandising/documents/files';
import type { DocumentFileItem, DocumentUploadDetail } from '@/types';

type Props = {
    upload: DocumentUploadDetail;
    files: DocumentFileItem[];
    allowedExtensions: string[];
};

/**
 * One batch, and the files in it.
 *
 * **Not paginated.** The batch is capped at `max_files_per_batch` by the upload
 * itself, so the page is bounded by construction — the same argument the BQS detail
 * page makes about a workbook's rows.
 *
 * Downloads are plain `<a>` links to a route, not to a file: the disk is private, so
 * every read is checked against the permission and the buyer scope. Nothing here is
 * parsed, so there is no parse status, no warning list and no revision chain — the
 * three things every other Merchandising detail page is mostly made of.
 */
export default function DocumentsShow({
    upload,
    files,
    allowedExtensions,
}: Props) {
    const canDelete = useCan('merchandising.documents.delete');
    const canUpdate = useCan('merchandising.documents.update');

    /* Replace destroys the file it replaces, so it takes both. */
    const canReplace = canUpdate && canDelete;

    const [previewing, setPreviewing] = useState<DocumentFileItem | null>(null);
    const [replacing, setReplacing] = useState<DocumentFileItem | null>(null);

    const subtitle = [
        upload.file_type_label,
        upload.buyer ?? 'Any buyer',
        upload.uploaded_by ? `uploaded by ${upload.uploaded_by}` : null,
        upload.created_at,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={upload.title ?? 'Documents'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Returns to the list *as it was* — filters, sort and page —
                    which the breadcrumb above deliberately does not. */}
                <div>
                    <BackLink fallback={index().url} label="Documents" />
                </div>

                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={upload.title ?? 'Untitled upload'}
                        description={subtitle}
                    />

                    {canDelete && (
                        <ConfirmDeleteDialog
                            submit={destroyBatch.form(upload.id)}
                            title="Delete this upload"
                            description={`All ${upload.file_count} file(s) in this upload will be permanently deleted. This cannot be undone.`}
                            confirmLabel="Delete upload"
                            testId="delete-document-upload"
                        />
                    )}
                </div>

                {upload.note && (
                    <p className="max-w-3xl text-sm text-base-content/70">
                        {upload.note}
                    </p>
                )}

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th className="text-right">Size</th>
                                <th>Added</th>
                                <th className="w-48" />
                            </tr>
                        </thead>

                        <tbody>
                            {files.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="text-center text-base-content/60"
                                    >
                                        Every file in this upload has been
                                        deleted.
                                    </td>
                                </tr>
                            )}

                            {files.map((file) => (
                                <tr key={file.id}>
                                    <td className="max-w-96 truncate font-medium">
                                        {file.original_name}
                                    </td>

                                    <td>
                                        <span className="badge badge-ghost badge-sm uppercase">
                                            {file.extension || '—'}
                                        </span>
                                    </td>

                                    <td className="text-right tabular-nums">
                                        {formatBytes(file.size_bytes)}
                                    </td>

                                    <td className="tabular-nums">
                                        {file.updated_at ?? file.created_at}
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {/* Only what the browser can render
                                                in place — an Office file gets
                                                the download below instead. */}
                                            {file.is_previewable && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Preview ${file.original_name}`}
                                                    onClick={() =>
                                                        setPreviewing(file)
                                                    }
                                                    data-test="preview-document-file"
                                                >
                                                    <Eye />
                                                </Button>
                                            )}

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Download ${file.original_name}`}
                                                asChild
                                            >
                                                <a
                                                    href={
                                                        download({
                                                            documentUpload:
                                                                upload.id,
                                                            documentFile:
                                                                file.id,
                                                        }).url
                                                    }
                                                    data-test="download-document-file"
                                                >
                                                    <Download />
                                                </a>
                                            </Button>

                                            {canReplace && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Replace ${file.original_name}`}
                                                    onClick={() =>
                                                        setReplacing(file)
                                                    }
                                                    data-test="replace-document-file"
                                                >
                                                    <RefreshCw />
                                                </Button>
                                            )}

                                            {canDelete && (
                                                <ConfirmDeleteDialog
                                                    submit={destroyFile.form({
                                                        documentUpload:
                                                            upload.id,
                                                        documentFile: file.id,
                                                    })}
                                                    title={`Delete ${file.original_name}`}
                                                    description="The file is permanently deleted. This cannot be undone."
                                                    testId="delete-document-file"
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <DocumentPreviewDialog
                uploadId={upload.id}
                file={previewing}
                onOpenChange={(open) => !open && setPreviewing(null)}
            />

            {canReplace && (
                <DocumentReplaceDialog
                    uploadId={upload.id}
                    file={replacing}
                    allowedExtensions={allowedExtensions}
                    onOpenChange={(open) => !open && setReplacing(null)}
                />
            )}
        </>
    );
}

DocumentsShow.layout = ({ upload }: { upload: DocumentUploadDetail }) => ({
    breadcrumbs: [
        { title: 'Documents', href: index() },
        { title: upload.title ?? 'Untitled upload', href: show(upload.id) },
    ],
});
