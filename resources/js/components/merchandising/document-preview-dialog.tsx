import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { download, preview } from '@/routes/merchandising/documents/files';
import type { DocumentFileItem } from '@/types';

type Props = {
    uploadId: number;
    file: DocumentFileItem | null;
    onOpenChange: (open: boolean) => void;
};

/** Extensions rendered as a picture rather than in a frame. */
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

/**
 * Render one file in place, when the browser can.
 *
 * **No converter is involved, and none is coming.** Turning an `.xlsx` or a `.docx`
 * into something previewable is reading it, which this surface does not do — so an
 * Office file gets a download prompt rather than a rendering. The server decides what
 * may be shown inline (`previewable_extensions`), and this only chooses between an
 * `<img>` and an `<iframe>` among what it allowed.
 *
 * The source is a route, not a public URL: the disk is private, so every read is
 * checked against the permission and the buyer scope. `preview` sends
 * `X-Content-Type-Options: nosniff` and only ever sends `Content-Disposition: inline`
 * for an allow-listed extension — `svg` and `html` are absent from that list, because
 * an inline SVG served from this origin is stored XSS.
 *
 * **The file is a prop rather than state**, so `DialogContent` unmounting on close
 * (ARCHITECTURE.md §8.7) discards the frame and its request with it — a large PDF does
 * not keep streaming behind a closed dialog.
 */
export default function DocumentPreviewDialog({
    uploadId,
    file,
    onOpenChange,
}: Props) {
    const open = file !== null;

    const source = file
        ? preview({ documentUpload: uploadId, documentFile: file.id }).url
        : '';

    const isImage = file
        ? IMAGE_EXTENSIONS.includes(file.extension.toLowerCase())
        : false;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-5xl">
                <DialogTitle className="truncate">
                    {file?.original_name ?? 'Preview'}
                </DialogTitle>

                <DialogDescription>
                    {file
                        ? `${file.extension.toUpperCase()} · ${formatBytes(file.size_bytes)}`
                        : ''}
                </DialogDescription>

                {file && (
                    <div className="mt-4 overflow-auto rounded-box border border-base-300/70 bg-base-200">
                        {isImage ? (
                            <img
                                src={source}
                                alt={file.original_name}
                                className="mx-auto max-h-[70vh] object-contain"
                                data-test="document-preview-image"
                            />
                        ) : (
                            <iframe
                                src={source}
                                title={file.original_name}
                                className="h-[70vh] w-full"
                                data-test="document-preview-frame"
                            />
                        )}
                    </div>
                )}

                <DialogFooter className="gap-2">
                    {file && (
                        <Button variant="secondary" asChild>
                            <a
                                href={
                                    download({
                                        documentUpload: uploadId,
                                        documentFile: file.id,
                                    }).url
                                }
                                data-test="download-from-preview"
                            >
                                <Download /> Download
                            </a>
                        </Button>
                    )}

                    <DialogClose asChild>
                        <Button type="button">Close</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * A file size somebody can read.
 *
 * Exported so the file list and this dialog agree; there is no size formatter in
 * `lib/` yet, and one caller does not justify promoting it.
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}
