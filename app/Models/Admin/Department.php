<?php

namespace App\Models\Admin;

use App\Concerns\Audited;
use App\Concerns\BuyerScoped;
use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Contracts\Auditable;
use App\Enums\FilterType;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Admin\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer's own merchandise department — `GIRLSWEAR`, `BOYSWEAR`, `MENSWEAR`.
 *
 * **This is the buyer's classification of what they buy, not an internal org
 * unit**, which is why every department belongs to exactly one buyer and why the
 * same name legitimately appears once per buyer. See ARCHITECTURE.md §9.4.
 *
 * **Descriptive only.** A department grants nothing — permissions come from roles
 * (ARCHITECTURE.md §9.1) and row visibility from buyer access (§9.2). Never
 * branch on a department in an authorization check.
 *
 * **Nothing references a department yet.** Merchandising carries its department
 * as free text on `bqs_sheets` and `bqs_rows`, where it is also component #3 of
 * the `Merchandising\BqsRowKey` hash — normalising it into a foreign key would
 * change every stored `row_key` and make every held BQS read as new. That
 * reconciliation waits for the BQS re-architecture; see
 * {@see \App\Services\Admin\DepartmentService::deletionBlocker()}.
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $name
 * @property string|null $code
 * @property RecordStatus $status
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['buyer_id', 'name', 'code', 'status'])]
class Department extends Model implements Auditable
{
    /** @use HasFactory<DepartmentFactory> */
    use Audited, BuyerScoped, HasFactory, HasStatus, Listable;

    /**
     * The columns the department list's filter row exposes.
     *
     * `buyer_id` is {@see FilterType::Equals} and rendered as a dropdown. It is a
     * column on this table rather than a join, so the cell costs nothing — and it
     * is worth having because the buyer is what groups this list. Note it narrows
     * *within* what {@see \App\Models\Scopes\BuyerScope} already allows; the scope
     * is the access control and this is only a filter.
     *
     * `name` is {@see FilterType::Contains} — finding "wear" inside "GIRLSWEAR" is
     * what somebody typing here wants, and the table is small enough for the scan.
     * `code` is {@see FilterType::Prefix}, which is both how anybody types a code
     * and what keeps its unique index seekable. Never infer either from the column
     * type; both are `varchar` (ARCHITECTURE.md §6.3).
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'buyer_id' => FilterType::Equals,
        'name' => FilterType::Contains,
        'code' => FilterType::Prefix,
        'status' => FilterType::Equals,
    ];

    /**
     * The columns the department list may be sorted by.
     *
     * `buyer_id` is deliberately absent: sorting by a buyer's *id* orders by
     * nothing a reader recognises, and sorting by its name means ordering on a
     * joined column, a query shape this list does not have. Filter instead — the
     * same trade the users list makes for its designation column.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'code', 'status', 'created_at'];

    /**
     * The buyer this department belongs to — the unit ARCHITECTURE.md §9.2 scopes by.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The user who created this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * The user who last changed this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
