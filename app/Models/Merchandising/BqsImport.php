<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Concerns\BuyerScoped;
use App\Enums\Merchandising\BqsFileType;
use App\Enums\Merchandising\BqsParseStatus;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Merchandising\BqsImportService;
use Database\Factories\Merchandising\BqsImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One uploaded BQS workbook, and what the reader made of it.
 *
 * The file; {@see BqsSheet} is the thing the file described. Keeping them apart is
 * what lets an upload answered with **skip** still leave a record — the workbook was
 * received, and that is a fact worth holding even though it produced no revision.
 *
 * `payload` holds the resolved header map and every warning, so an unmapped column
 * stays next to the workbook that had it. Buyer-owned, and filtered by the same scope
 * as everything else (ARCHITECTURE.md §9.2).
 *
 * `bqs_date` is here as well as on the sheet because a **staged** import must remember
 * it: the date was entered on the upload form, and the sheet it will eventually become
 * does not exist until the uploader answers the collision.
 *
 * @phpstan-import-type StagedRows from BqsImportService
 *
 * @property int $id
 * @property int $buyer_id
 * @property Carbon $bqs_date
 * @property string $source_file_name
 * @property string|null $stored_path
 * @property BqsFileType $detected_file_type
 * @property string $sheet_name
 * @property string $header_fingerprint
 * @property int $row_count
 * @property BqsParseStatus $parse_status
 * @property string $source_hash
 * @property array<string, mixed> $payload
 * @property StagedRows|null $staged_rows
 * @property int|null $inserted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read Collection<int, BqsSheet> $sheets
 * @property-read User|null $insertedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'buyer_id', 'bqs_date', 'source_file_name', 'stored_path', 'detected_file_type',
    'sheet_name', 'header_fingerprint', 'row_count', 'parse_status', 'source_hash',
    'payload', 'staged_rows',
])]
class BqsImport extends Model implements Auditable
{
    /** @use HasFactory<BqsImportFactory> */
    use Audited, BuyerScoped, HasFactory;

    /**
     * Columns the audit trail records the *change* of, never the content.
     *
     * `payload` is the whole parse result of a workbook and `staged_rows` is every
     * BQS awaiting a decision. Audited, each update would write two more copies of
     * them into `audit_logs` — the trail would become a larger duplicate of the
     * table it describes, and a single row could reach megabytes.
     *
     * Nothing is lost that this table does not already hold: the payload is kept
     * on the import row itself, which is never rewritten in place. What the trail
     * still answers is who imported, when, from where, and how the status and
     * staging moved — which is the question anybody actually brings to it.
     *
     * @var array<int, string>
     */
    protected $auditExclude = ['payload', 'staged_rows'];

    /**
     * Imports still holding a BQS nobody has decided about.
     *
     * Pending *is* having staged rows — there is no separate status column, for the
     * reason `PoImport::scopePending()` gives: a second field would only be a way for
     * the two to disagree.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNotNull('staged_rows');
    }

    /**
     * The buyer whose workbook this was.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The BQS revisions written from this workbook.
     *
     * `hasMany` rather than `hasOne` even though one upload writes at most one sheet:
     * an **overwrite** deletes the sheet an earlier import produced, and modelling
     * this as a single relation would make that read as the import losing its sheet
     * rather than the sheet being replaced.
     *
     * @return HasMany<BqsSheet, $this>
     */
    public function sheets(): HasMany
    {
        return $this->hasMany(BqsSheet::class);
    }

    /**
     * The user who uploaded the workbook, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bqs_date' => 'date',
            'detected_file_type' => BqsFileType::class,
            'parse_status' => BqsParseStatus::class,
            'payload' => 'array',
            'staged_rows' => 'array',
        ];
    }
}
