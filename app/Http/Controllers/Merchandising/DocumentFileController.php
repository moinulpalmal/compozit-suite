<?php

namespace App\Http\Controllers\Merchandising;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\DocumentFileReplaceRequest;
use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use App\Services\Merchandising\DocumentLibraryService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves and edits the individual files inside a document upload.
 *
 * **Every action here takes its batch as well as its file, and the routes are declared
 * with `->scopeBindings()`.** That is not tidiness. {@see DocumentFile} carries no
 * `buyer_id` and no global scope — it reaches its buyer through its parent, which is
 * the ARCHITECTURE.md §9.2 rule for a child table — so a route of the form
 * `/documents/files/{documentFile}` would resolve a file belonging to a batch the
 * actor cannot see and hand it over. Scoped binding resolves the file through
 * `$documentUpload->files()`, so the parent's buyer scope decides first and a
 * mismatched pair 404s.
 *
 * **Files are streamed, never linked.** The disk is private and there is no public
 * URL, so `view` is checked on every read rather than once at upload time.
 */
class DocumentFileController extends Controller
{
    public function __construct(protected DocumentLibraryService $library) {}

    /**
     * Send a file back under the name it arrived with.
     */
    public function download(DocumentUpload $documentUpload, DocumentFile $documentFile): StreamedResponse
    {
        $this->assertStored($documentFile);

        return $this->disk()->download($documentFile->stored_path, $documentFile->original_name);
    }

    /**
     * Render a file in the browser.
     *
     * Two headers here are load-bearing:
     *
     * - **`Content-Disposition: inline` is only ever sent for an allow-listed
     *   extension.** `svg` and `html` are absent from that list and must stay absent —
     *   anything script-bearing rendered inline from this application's own origin is
     *   stored XSS, and the config file says so beside the list.
     * - **`X-Content-Type-Options: nosniff`** stops a browser second-guessing the
     *   `Content-Type` and rendering a mislabelled file as something executable. The
     *   stored MIME type came from the uploader's browser and is not trusted; the
     *   header is what makes trusting it survivable.
     *
     * Anything not previewable falls back to a download, which is also what the page
     * offers — no converter is involved, and none is going to be.
     */
    public function preview(DocumentUpload $documentUpload, DocumentFile $documentFile): StreamedResponse
    {
        $this->assertStored($documentFile);

        if (! $documentFile->isPreviewable()) {
            return $this->disk()->download($documentFile->stored_path, $documentFile->original_name);
        }

        return $this->disk()->response($documentFile->stored_path, $documentFile->original_name, [
            'Content-Type' => $documentFile->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($documentFile->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Put a new file in this one's place.
     *
     * `POST` rather than `PUT` because a browser form cannot send a multipart body
     * with `PUT`. Authorization — `update` **and** `delete`, because the old file is
     * destroyed — is in {@see DocumentFileReplaceRequest}.
     */
    public function update(
        DocumentFileReplaceRequest $request,
        DocumentUpload $documentUpload,
        DocumentFile $documentFile,
    ): RedirectResponse {
        $previousName = $documentFile->original_name;

        $this->library->replace($documentFile, $request->file('file'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Replaced :name with :replacement.', [
                'name' => $previousName,
                'replacement' => $documentFile->original_name,
            ]),
        ]);

        return to_route('merchandising.documents.show', $documentUpload);
    }

    /**
     * Remove one file from its batch.
     *
     * The batch survives losing its last file — see
     * {@see DocumentLibraryService::deleteFile()} for why.
     */
    public function destroy(DocumentUpload $documentUpload, DocumentFile $documentFile): RedirectResponse
    {
        $name = $documentFile->original_name;

        $this->library->deleteFile($documentFile);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name.', ['name' => $name]),
        ]);

        return to_route('merchandising.documents.show', $documentUpload);
    }

    /**
     * Refuse a row whose object is not on the disk.
     *
     * A 404 rather than a 500: the row is real and the actor is entitled to it, but
     * there is nothing to send. It happens when a disk is restored without its
     * database or the other way round, and a stack trace is a worse answer than a
     * missing page.
     */
    private function assertStored(DocumentFile $file): void
    {
        abort_unless($this->disk()->exists($file->stored_path), 404);
    }

    /**
     * The private disk the library lives on.
     */
    private function disk(): Filesystem
    {
        return Storage::disk((string) config('merchandising-documents.storage.disk'));
    }
}
