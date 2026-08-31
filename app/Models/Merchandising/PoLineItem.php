<?php

namespace App\Models\Merchandising;

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
 */
#[Fillable([
    'purchase_order_id', 'pack_number', 'pack_description', 'assortment_id', 'vendor_stock',
    'color', 'size', 'quantity', 'item_number', 'vendor_stock_number', 'mfg_stock_number',
    'product_number', 'upc_number', 'item_description1', 'item_description2',
    'upc_description', 'signing_description', 'uom_qty', 'uom_code',
])]
class PoLineItem extends Model
{
    /** @use HasFactory<PoLineItemFactory> */
    use HasFactory;

    /**
     * The order this line belongs to.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
