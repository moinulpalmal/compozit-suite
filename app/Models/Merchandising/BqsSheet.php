<?php

namespace App\Models\Merchandising;

use App\Concerns\BuyerScoped;
use App\Concerns\Listable;
use App\Enums\FilterType;
use App\Enums\Merchandising\BqsParseStatus;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Merchandising\BqsRowKey;
use Database\Factories\Merchandising\BqsSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A BQS — the buyer's buy plan for one product program — as one revision of it.
 *
 * Buyer-owned; the `BuyerScoped` `use` below is the whole registration
 * (ARCHITECTURE.md §9.2), and it is what makes the child rows safe to leave unscoped.
 *
 * **Revisions are keyed on nothing the workbook contains**, because it contains
 * nothing to key them on. {@see BqsRowKey} explains the row-key intersection rule that
 * stands in for a document number, and the `create_bqs_sheets_table` migration records
 * why `root_id` exists rather than a unique over the file name.
 *
 * @property int $id
 * @property int $bqs_import_id
 * @property int $buyer_id
 * @property int|null $root_id
 * @property Carbon $bqs_date
 * @property string|null $fye
 * @property string|null $season
 * @property string|null $department
 * @property string $title
 * @property int $revision_no
 * @property bool $is_current
 * @property string $source_hash
 * @property int $row_count
 * @property BqsParseStatus $parse_status
 * @property array<string, mixed> $payload
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read BqsImport $import
 * @property-read BqsSheet|null $root
 * @property-read Collection<int, BqsSheet> $revisions
 * @property-read Collection<int, BqsRow> $rows
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'bqs_import_id', 'buyer_id', 'root_id', 'bqs_date', 'fye', 'season', 'department',
    'title', 'revision_no', 'is_current', 'source_hash', 'row_count', 'parse_status', 'payload',
])]
class BqsSheet extends Model
{
    /** @use HasFactory<BqsSheetFactory> */
    use BuyerScoped, HasFactory, Listable;

    /**
     * The columns the BQS list's filter row exposes.
     *
     * `title` is {@see FilterType::Contains} — it is the workbook's file name and
     * people remember the middle of one ("skater dress"), not its opening. `fye`,
     * `season` and `department` are {@see FilterType::Equals} because they are short
     * codes from a small set and a dropdown serves them better than typing.
     * `bqs_date` is {@see FilterType::Prefix} so `2026` and `2026-09` both narrow.
     *
     * Never infer any of this from the column type — all five are `varchar` or `date`
     * (ARCHITECTURE.md §6.3).
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'title' => FilterType::Contains,
        'fye' => FilterType::Equals,
        'season' => FilterType::Equals,
        'department' => FilterType::Contains,
        'bqs_date' => FilterType::Prefix,
        'parse_status' => FilterType::Equals,
    ];

    /**
     * The columns the BQS list may be sorted by.
     *
     * `revision_no` is here so a BQS's history reads in order. `row_count` is a stored
     * column, not an aggregate — ARCHITECTURE.md §8.6 keeps aggregates out of both
     * lists, and this one is written at import precisely so it can be sorted on.
     *
     * @var list<string>
     */
    public const array SORTABLE = [
        'title', 'bqs_date', 'fye', 'season', 'department',
        'revision_no', 'row_count', 'parse_status', 'created_at',
    ];

    /**
     * Only the newest revision of each BQS.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * Only the BQS records a downstream module may rely on.
     *
     * Mirrors `PurchaseOrder::scopeUsable()`. Nothing writes a {@see
     * BqsParseStatus::Failed} sheet today — a workbook that cannot be read is refused
     * before a sheet exists — but the scope is here so a reader never has to know
     * that, and so the day a partial import is allowed does not silently widen what
     * Production trusts.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('parse_status', '!=', BqsParseStatus::Failed->value);
    }

    /**
     * The buyer this BQS belongs to — the unit ARCHITECTURE.md §9.2 scopes by.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The upload this revision was read out of, with the workbook and every warning.
     *
     * @return BelongsTo<BqsImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(BqsImport::class, 'bqs_import_id');
    }

    /**
     * The first revision of this BQS. Revision 1 is its own root.
     *
     * @return BelongsTo<BqsSheet, $this>
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(BqsSheet::class, 'root_id');
    }

    /**
     * Every revision of this BQS, this one included.
     *
     * @return HasMany<BqsSheet, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(BqsSheet::class, 'root_id', 'root_id');
    }

    /**
     * The style/colour lines of this BQS.
     *
     * @return HasMany<BqsRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(BqsRow::class);
    }

    /**
     * The user who imported this revision, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * The user who last changed this revision, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bqs_date' => 'date',
            'is_current' => 'boolean',
            'parse_status' => BqsParseStatus::class,
            'payload' => 'array',
        ];
    }
}
