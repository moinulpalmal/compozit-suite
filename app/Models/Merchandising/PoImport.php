<?php

namespace App\Models\Merchandising;

use App\Concerns\BuyerScoped;
use App\Enums\Merchandising\PoFileType;
use App\Enums\Merchandising\PoParseStatus;
use App\Models\Admin\Buyer;
use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Merchandising\PurchaseOrderImportService;
use Database\Factories\Merchandising\PoImportFactory;
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
 * One uploaded purchase-order document, and what the parser made of it.
 *
 * This is the record that makes a `failed` purchase order diagnosable: `payload` holds
 * the complete parse result, warnings included, and `stored_path` points at the file
 * it came from. Without it, a rejected import would leave nothing behind to look at.
 *
 * Buyer-owned like the orders it produces, so it is filtered by the same scope
 * (ARCHITECTURE.md §9.2).
 *
 * `staged_orders` holds the purchase orders this document could not write without
 * asking — see {@see PurchaseOrderImportService::resolve()}, which also defines their
 * shape, because it is the only thing that builds one.
 *
 * @phpstan-import-type StagedOrder from PurchaseOrderImportService
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $source_file_name
 * @property string|null $stored_path
 * @property PoFileType $detected_file_type
 * @property string $template_fingerprint
 * @property int $page_count
 * @property int $po_count
 * @property PoParseStatus $parse_status
 * @property string $confidence
 * @property array<string, mixed> $payload
 * @property list<StagedOrder>|null $staged_orders
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Buyer $buyer
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read User|null $insertedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'buyer_id', 'source_file_name', 'stored_path', 'detected_file_type', 'template_fingerprint',
    'page_count', 'po_count', 'parse_status', 'confidence', 'payload', 'staged_orders',
])]
class PoImport extends Model
{
    /** @use HasFactory<PoImportFactory> */
    use BuyerScoped, HasFactory;

    /**
     * Imports still holding purchase orders nobody has decided about.
     *
     * Pending *is* having staged orders — there is no separate status column, because
     * a second field would only be a way for the two to disagree. See the
     * `add_staged_orders_to_po_imports_table` migration.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNotNull('staged_orders');
    }

    /**
     * The buyer whose document this was.
     *
     * @return BelongsTo<Buyer, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    /**
     * The purchase orders read out of this document — one file holds several.
     *
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * The user who uploaded the document, if there was an authenticated actor.
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
            'detected_file_type' => PoFileType::class,
            'parse_status' => PoParseStatus::class,
            'payload' => 'array',
            'staged_orders' => 'array',
        ];
    }
}
