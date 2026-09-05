<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Concerns\BuyerScoped;
use App\Concerns\Listable;
use App\Enums\FilterType;
use App\Enums\Merchandising\PoParseStatus;
use App\Enums\Merchandising\PoType;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Merchandising\PurchaseOrderFactory;
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
 * A purchase order imported from a buyer's document.
 *
 * **The application's first buyer-owned model** — the one ARCHITECTURE.md §9.2's
 * `BuyerScoped` docblock uses as its worked example. The `use` below is the whole
 * registration; every query is filtered by the signed-in user's buyer access unless a
 * caller says `withoutBuyerScope()` and says why.
 *
 * The header is columns and everything else is `payload`; the reasoning, and the
 * revision design behind `source_hash` / `revision_no` / `is_current`, are in the
 * `create_purchase_orders_table` migration and `documentation/merchandising.md`.
 *
 * **A `failed` row is stored on purpose and is not trustworthy order data.** Anything
 * reading this table for facts must exclude it — {@see self::scopeUsable()} is the
 * intended way, and `documentation/merchandising.md` records the hazard.
 *
 * @property int $id
 * @property int $po_import_id
 * @property int $buyer_id
 * @property string $po_number
 * @property int $revision_no
 * @property Carbon|null $revised_at
 * @property string|null $revised_by
 * @property string $source_hash
 * @property bool $is_current
 * @property string|null $document_status
 * @property string|null $quote_id
 * @property PoType|null $po_type
 * @property Carbon|null $create_date
 * @property Carbon|null $negotiation_date
 * @property Carbon|null $vendor_ship_date
 * @property Carbon|null $cancel_date
 * @property string|null $currency
 * @property string|null $exchange_rate
 * @property int|null $total_cartons
 * @property int|null $total_qty
 * @property string|null $total_weight_kgs
 * @property string|null $total_volume_cbm
 * @property string|null $net_first_cost_usd
 * @property string|null $net_first_cost_cnd
 * @property string|null $vendor_name
 * @property string|null $factory_id
 * @property string|null $factory_name
 * @property string $template_fingerprint
 * @property PoParseStatus $parse_status
 * @property string $confidence
 * @property array<string, mixed> $payload
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read PoImport $import
 * @property-read Collection<int, PoLineItem> $lineItems
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'po_import_id', 'buyer_id', 'po_number', 'revision_no', 'revised_at', 'revised_by',
    'source_hash', 'is_current', 'document_status', 'quote_id', 'po_type', 'create_date',
    'negotiation_date', 'vendor_ship_date', 'cancel_date', 'currency', 'exchange_rate',
    'total_cartons', 'total_qty', 'total_weight_kgs', 'total_volume_cbm',
    'net_first_cost_usd', 'net_first_cost_cnd', 'vendor_name', 'factory_id', 'factory_name',
    'template_fingerprint', 'parse_status', 'confidence', 'payload',
])]
class PurchaseOrder extends Model implements Auditable
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use Audited, BuyerScoped, HasFactory, Listable;

    /**
     * `payload` is recorded as having changed, never copied.
     *
     * It is roughly ten document sections of parsed order, and every one of the
     * twenty-nine header columns beside it *is* audited — so a revision still
     * shows its dates, quantities, costs, vendor and `is_current` moving. What the
     * trail declines to hold is a second copy of the document, per
     * {@see PoImport::$auditExclude}.
     *
     * @var array<int, string>
     */
    protected $auditExclude = ['payload'];

    /**
     * The columns the purchase-order list's filter row exposes.
     *
     * `po_number` is {@see FilterType::Prefix}: it is an identifier, it is how anyone
     * types one, and it keeps the leading edge of the unique indexes seekable. The two
     * names are {@see FilterType::Contains}, where finding mid-string is worth the
     * scan. The rest are dropdowns and match exactly. Never infer any of this from the
     * column type — all six are `varchar` (ARCHITECTURE.md §6.3).
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'po_number' => FilterType::Prefix,
        'vendor_name' => FilterType::Contains,
        'factory_name' => FilterType::Contains,
        'document_status' => FilterType::Equals,
        'parse_status' => FilterType::Equals,
        'currency' => FilterType::Equals,
    ];

    /**
     * The columns the purchase-order list may be sorted by.
     *
     * `revision_no` is here so an order's history reads in order on the revisions
     * view. Aggregates are absent by design — ARCHITECTURE.md §8.6.
     *
     * @var list<string>
     */
    public const array SORTABLE = [
        'po_number', 'revision_no', 'revised_at', 'vendor_ship_date',
        'cancel_date', 'total_qty', 'parse_status', 'created_at',
    ];

    /**
     * Only the newest revision of each purchase order.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * Only the orders a downstream module may rely on.
     *
     * Excludes {@see PoParseStatus::Failed} rows, which are kept for diagnosis and are
     * known to be wrong. Production and Reports should read through this rather than
     * remembering the condition.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('parse_status', '!=', PoParseStatus::Failed->value);
    }

    /**
     * The buyer this order belongs to — the unit ARCHITECTURE.md §9.2 scopes by.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The upload this order was read out of, with the document and every warning.
     *
     * @return BelongsTo<PoImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(PoImport::class, 'po_import_id');
    }

    /**
     * The colour/size lines of every pack on this order.
     *
     * @return HasMany<PoLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(PoLineItem::class);
    }

    /**
     * The user who imported this record, if there was an authenticated actor.
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revised_at' => 'datetime',
            'create_date' => 'date',
            'negotiation_date' => 'date',
            'vendor_ship_date' => 'date',
            'cancel_date' => 'date',
            'is_current' => 'boolean',
            'po_type' => PoType::class,
            'parse_status' => PoParseStatus::class,
            'payload' => 'array',
        ];
    }
}
