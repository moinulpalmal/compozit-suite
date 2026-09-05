<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Contracts\Auditable;
use App\Enums\Merchandising\BqsLinkSource;
use App\Services\Merchandising\BqsPoLinker;
use Database\Factories\Merchandising\PoLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One colour/size line of an imported purchase order.
 *
 * The level at which quantity is ordered, and therefore the level Production computes
 * consumption from — which is the whole reason these are rows rather than a slice of
 * the order's JSON payload.
 *
 * **This model is deliberately not `BuyerScoped`.** It has no `buyer_id`, and
 * ARCHITECTURE.md §9.2 is explicit that a model reaching its buyer through a parent
 * needs its own column rather than a scope that joins. Reach line items through
 * {@see PurchaseOrder::lineItems()}, which *is* scoped; never query this table
 * unqualified for anything a user will see.
 *
 * It carries no `inserted_by`/`last_updated_by` and no observer: a line item is
 * written only by the import that created its order, and is never edited by hand. The
 * actor is on the order.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $pack_number
 * @property string|null $pack_description
 * @property string|null $assortment_id
 * @property string|null $vendor_stock
 * @property string|null $color
 * @property string|null $size
 * @property int|null $quantity
 * @property int|null $total_cartons_per_line
 * @property int|null $bqs_row_id
 * @property BqsLinkSource|null $bqs_link_source
 * @property string|null $item_number
 * @property string|null $vendor_stock_number
 * @property string|null $mfg_stock_number
 * @property string|null $product_number
 * @property string|null $upc_number
 * @property string|null $item_description1
 * @property string|null $item_description2
 * @property string|null $upc_description
 * @property string|null $signing_description
 * @property string|null $uom_qty
 * @property string|null $uom_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read BqsRow|null $bqsRow
 */
#[Fillable([
    'purchase_order_id', 'pack_number', 'pack_description', 'assortment_id', 'vendor_stock',
    'color', 'size', 'quantity', 'total_cartons_per_line', 'item_number', 'vendor_stock_number',
    'mfg_stock_number', 'product_number', 'upc_number', 'item_description1', 'item_description2',
    'upc_description', 'signing_description', 'uom_qty', 'uom_code',
])]
class PoLineItem extends Model implements Auditable
{
    /** @use HasFactory<PoLineItemFactory> */
    use Audited, HasFactory;

    /**
     * The order this line belongs to.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The BQS row that planned this line, once it is known.
     *
     * Null is a normal state, not a gap: a colour can be ordered that no BQS ever
     * planned — `TEAL-ICY MORN` on the reference document — and a colour can await a
     * person's decision, because matching is strict. {@see BqsPoLinker} is the only
     * thing that writes this.
     *
     * **`bqs_row_id` and `bqs_link_source` are not mass-assignable.** The linker sets
     * them with `forceFill`, so there is exactly one write path and a stray `update()`
     * elsewhere cannot invent a link.
     *
     * @return BelongsTo<BqsRow, $this>
     */
    public function bqsRow(): BelongsTo
    {
        return $this->belongsTo(BqsRow::class, 'bqs_row_id');
    }

    /**
     * How many garments this line actually orders.
     *
     * **`quantity` alone is not that number.** It is the size ratio *inside one pack*:
     * on the reference document the five sizes of a colour read 3, 4, 4, 2, 1 — summing
     * to the fourteen of "14PC GR SS SKATER DRESS" — and `Total Cartons per Line` says
     * how many such packs were ordered. The product is the ordered quantity:
     *
     * ```text
     * 14 × 393 = 5,502, which is the BQS row's Initial Set Units / Store exactly
     * ```
     *
     * Anything comparing an order against a plan must use this and not `quantity`;
     * summing the ratios reports 14 against 5,502 and reads as a catastrophic
     * shortfall. Null when either half is missing, so a partial parse cannot silently
     * contribute a wrong figure to a total.
     */
    public function orderedUnits(): ?int
    {
        if ($this->quantity === null || $this->total_cartons_per_line === null) {
            return null;
        }

        return $this->quantity * $this->total_cartons_per_line;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bqs_link_source' => BqsLinkSource::class,
        ];
    }
}
