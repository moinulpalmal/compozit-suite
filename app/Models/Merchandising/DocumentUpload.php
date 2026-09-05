<?php

namespace App\Models\Merchandising;

use App\Concerns\BuyerScoped;
use App\Concerns\BuyerScopedOrGlobal;
use App\Concerns\Listable;
use App\Enums\FilterType;
use App\Enums\Merchandising\DocumentType;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Merchandising\DocumentLibraryService;
use Database\Factories\Merchandising\DocumentUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One batch of files somebody uploaded, and what they called it.
 *
 * **Nothing here was parsed**, which is the whole difference between this and
 * {@see BqsImport} or {@see PoImport}. Those exist to hold what a parser made of a
 * file; this exists to hold the file. `file_type` is a label the uploader chose, so a
 * batch typed {@see DocumentType::Bqs} is *not* an imported BQS and produces no
 * {@see BqsSheet} — see ARCHITECTURE.md §5, Module 3.
 *
 * **The buyer is optional**, and a null one means everybody with the view permission
 * sees it. {@see BuyerScopedOrGlobal} is what makes that true rather than
 * {@see BuyerScoped}, which would hide an unassigned row from everyone
 * (§9.2). Its files reach their buyer through this row and carry no `buyer_id` of
 * their own.
 *
 * @property int $id
 * @property int|null $buyer_id
 * @property DocumentType $file_type
 * @property string|null $title
 * @property string|null $note
 * @property int $file_count
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer|null $buyer
 * @property-read Collection<int, DocumentFile> $documentFiles
 * @property-read User|null $insertedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['buyer_id', 'file_type', 'title', 'note', 'file_count'])]
class DocumentUpload extends Model
{
    /** @use HasFactory<DocumentUploadFactory> */
    use BuyerScopedOrGlobal, HasFactory, Listable;

    /**
     * The columns the document list's filter row exposes.
     *
     * `title` is `Contains` because it is prose somebody typed and finding it
     * mid-string is the point; `file_type` is a dropdown and therefore `Equals`
     * (ARCHITECTURE.md §6.3). There is no `buyer` cell — the column on this table is
     * an id, and a name filter would need a join the shared apparatus does not do;
     * the buyer is rendered as a column and narrowed by the scope instead.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'title' => FilterType::Contains,
        'file_type' => FilterType::Equals,
    ];

    /**
     * The columns the document list may be sorted by.
     *
     * `file_count` is a stored column rather than a `withCount` alias precisely so it
     * can appear here — §8.6 keeps aggregates out of both allow-lists.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['title', 'file_type', 'file_count', 'created_at'];

    /**
     * {@inheritDoc}
     *
     * Newest first: an inbox is read from the top, and `title` — the first entry in
     * `SORTABLE` — is nullable, so the base class's default would sort a batch nobody
     * named to one end of the list.
     */
    public static function defaultSort(): string
    {
        return 'created_at';
    }

    /**
     * The buyer this batch concerns, if it concerns one.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The files in the batch.
     *
     * This relationship is also the security boundary for every per-file route:
     * `->scopeBindings()` resolves `{documentFile}` through it, so a file can only be
     * reached under the batch that owns it. See {@see DocumentFile}.
     *
     * **The name is not redundant, it is load-bearing.** Laravel resolves a scoped
     * binding by calling `Str::plural()` on the route parameter, so `{documentFile}`
     * looks for `documentFiles()` and a shorter `files()` fails at runtime with
     * `Call to undefined method` — not with a 404, which is what makes it easy to
     * "tidy up" and only find out from a stack trace. Renaming this means renaming the
     * route parameter with it.
     *
     * @return HasMany<DocumentFile, $this>
     */
    public function documentFiles(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    /**
     * The user who uploaded the batch, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * The directory this batch's files live in, relative to the configured disk.
     *
     * On the model rather than in {@see DocumentLibraryService} because deleting a
     * batch and storing into it both need it, and a second spelling of a path is how
     * files get orphaned.
     */
    public function storageDirectory(): string
    {
        return trim((string) config('merchandising-documents.storage.root'), '/').'/'.$this->id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => DocumentType::class,
        ];
    }
}
