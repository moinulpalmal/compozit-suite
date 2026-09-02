<?php

namespace App\Services\Merchandising;

use App\Enums\Merchandising\DocumentType;
use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores, replaces and removes the files in the merchandising document library.
 *
 * **The only writer of the disk.** Every path this application builds under
 * `merchandising-documents/` is built here, which is what keeps the row and the object
 * in step: two spellings of a path is how a file becomes an orphan nothing can find
 * and nothing will ever delete.
 *
 * The ordering rule throughout is **write the disk inside the transaction, delete it
 * after the commit**. A stored object with no row is invisible litter; a row with no
 * object is a broken download somebody reports. So a failed batch rolls the rows back
 * and unlinks what it had already written, and a successful delete removes the object
 * only once the row is definitely gone.
 *
 * Nothing here reads a file. `file_type` arrives from the form and is stored as given
 * — see {@see DocumentType}.
 */
class DocumentLibraryService
{
    /**
     * Store a batch of uploaded files under one label.
     *
     * @param  list<UploadedFile>  $files
     * @param  array{buyer_id?: int|null, title?: string|null, note?: string|null}  $attributes
     */
    public function store(array $files, DocumentType $type, array $attributes = []): DocumentUpload
    {
        /** @var list<string> $written */
        $written = [];

        try {
            return DB::transaction(function () use ($files, $type, $attributes, &$written): DocumentUpload {
                $upload = DocumentUpload::create([
                    'buyer_id' => $attributes['buyer_id'] ?? null,
                    'file_type' => $type,
                    'title' => $attributes['title'] ?? null,
                    'note' => $attributes['note'] ?? null,
                    'file_count' => count($files),
                ]);

                foreach ($files as $file) {
                    $written[] = $this->writeFile($upload, $file);
                }

                return $upload;
            });
        } catch (\Throwable $exception) {
            /*
             * The rows are gone; the objects are not. Unlink what was written before
             * rethrowing, so a failure halfway through a twenty-file batch does not
             * leave nineteen unreferenced files on the disk forever.
             */
            $this->disk()->delete($written);

            throw $exception;
        }
    }

    /**
     * Put a new file in an existing one's place.
     *
     * **The file it replaces is destroyed.** There is no version chain — the decision
     * and its trade-off are recorded in `documentation/merchandising.md` — so the row
     * keeps its id and its position in the batch while everything describing the bytes
     * is rewritten. `ActorObserver` stamps `last_updated_by` on the way through, which
     * is the only record that a swap happened at all.
     */
    public function replace(DocumentFile $file, UploadedFile $replacement): DocumentFile
    {
        $previousPath = $file->stored_path;

        DB::transaction(function () use ($file, $replacement): void {
            $file->update($this->attributesFor($file->upload, $replacement));
        });

        /*
         * After the commit: if the update had rolled back, this would have deleted the
         * object the surviving row still points at.
         */
        if ($previousPath !== $file->stored_path) {
            $this->disk()->delete($previousPath);
        }

        return $file;
    }

    /**
     * Remove one file from a batch.
     *
     * The batch survives an empty one. A batch that has lost its last file is still a
     * record that somebody sent something and it was taken away, and deleting it here
     * would make `destroy` on a file sometimes navigate to a page that no longer
     * exists.
     */
    public function deleteFile(DocumentFile $file): void
    {
        $path = $file->stored_path;
        $upload = $file->upload;

        DB::transaction(function () use ($file, $upload): void {
            $file->delete();

            $upload->update(['file_count' => $upload->documentFiles()->count()]);
        });

        $this->disk()->delete($path);
    }

    /**
     * Remove a batch and every file in it.
     *
     * The child rows go with the parent through the cascading foreign key; the objects
     * go with the whole directory, which is why each batch gets one of its own.
     */
    public function deleteUpload(DocumentUpload $upload): void
    {
        $directory = $upload->storageDirectory();

        DB::transaction(function () use ($upload): void {
            $upload->delete();
        });

        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Write one uploaded file to the disk and record it.
     *
     * @return string the stored path, so the caller can unlink it if the batch fails
     */
    private function writeFile(DocumentUpload $upload, UploadedFile $file): string
    {
        $attributes = $this->attributesFor($upload, $file);

        $upload->documentFiles()->create($attributes);

        return $attributes['stored_path'];
    }

    /**
     * Describe an uploaded file, storing it in the process.
     *
     * **The uploader's filename never reaches the filesystem.** The object is named
     * with a ULID and only the extension survives, so a name cannot collide with
     * another batch's, cannot carry a traversal segment, and cannot be a name the
     * operating system treats specially. `original_name` is what the download response
     * gives back.
     *
     * @return array{original_name: string, stored_path: string, extension: string, mime_type: string, size_bytes: int, file_hash: string}
     */
    private function attributesFor(DocumentUpload $upload, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = (string) Str::ulid().($extension === '' ? '' : '.'.$extension);

        $hash = (string) hash_file('sha256', $file->getRealPath());
        $size = (int) $file->getSize();

        $path = $this->disk()->putFileAs($upload->storageDirectory(), $file, $name);

        return [
            /*
             * `getClientOriginalName()` is attacker-controlled, so it is stored for
             * display and never used to build a path. Basename-ing it removes any
             * directory a client tacked on before it reaches a download header.
             */
            'original_name' => basename($file->getClientOriginalName()),
            'stored_path' => is_string($path) ? $path : $upload->storageDirectory().'/'.$name,
            'extension' => $extension,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $size,
            'file_hash' => $hash,
        ];
    }

    /**
     * The private disk the library lives on.
     */
    private function disk(): Filesystem
    {
        return Storage::disk((string) config('merchandising-documents.storage.disk'));
    }
}
