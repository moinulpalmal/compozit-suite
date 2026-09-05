<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Merchandising\DocumentFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One file inside a {@see DocumentUpload}.
 *
 * **No buyer scope, and that is the §9.2 rule rather than an omission**: a file
 * reaches its buyer through its batch, exactly as `po_line_items` and `bqs_rows` reach
 * theirs. What the rule costs is that this model is *unscoped on its own*, so
 * resolving one straight from a URL would hand a file to somebody the batch is hidden
 * from. Every route that names a file therefore nests it under `{documentUpload}` and
 * is declared with `->scopeBindings()`; that is the whole guard, and there is nothing
 * in the database enforcing it.
 *
 * `stored_path` is built from a ULID and never contains {@see $original_name}. The
 * uploader's filename is restored by the download response only, which is what keeps a
 * hostile filename from reaching the filesystem at all.
 *
 * @property int $id
 * @property int $document_upload_id
 * @property string $original_name
 * @property string $stored_path
 * @property string $extension
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $file_hash
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DocumentUpload $upload
 * @property-read User|null $insertedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'document_upload_id', 'original_name', 'stored_path', 'extension',
    'mime_type', 'size_bytes', 'file_hash',
])]
class DocumentFile extends Model implements Auditable
{
    /** @use HasFactory<DocumentFileFactory> */
    use Audited, HasFactory;

    /**
     * The batch this file arrived in.
     *
     * @return BelongsTo<DocumentUpload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(DocumentUpload::class, 'document_upload_id');
    }

    /**
     * The user who uploaded this file, if there was an authenticated actor.
     *
     * Held per file rather than only on the batch because **replace** rewrites one
     * file without touching its siblings, and `last_updated_by` on the batch cannot
     * say which of twenty files somebody swapped.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * Whether a browser can render this file in place.
     *
     * A closed list from config, not a guess from the MIME type: the list decides what
     * the preview route is allowed to send inline, and anything that can carry script
     * must never be on it.
     */
    public function isPreviewable(): bool
    {
        /** @var list<string> $previewable */
        $previewable = config('merchandising-documents.previewable_extensions', []);

        return in_array(strtolower($this->extension), $previewable, true);
    }
}
