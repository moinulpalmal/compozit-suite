<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Contracts\Auditable;
use App\Services\Merchandising\BqsImportService;
use App\Services\Merchandising\BqsRowKey;
use Database\Factories\Merchandising\BqsRowFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One line of a BQS — a vendor style in one colourway.
 *
 * **No `buyer_id`, and therefore no `BuyerScoped`.** ARCHITECTURE.md §9.2 is explicit
 * that a model reaching its buyer through a parent needs its own column rather than a
 * scope that joins. Every read goes through {@see BqsSheet}, which is scoped, and the
 * foreign key cascades — the same position `PoLineItem` is in.
 *
 * `row_key` is this row's identity and, by intersection, the BQS's — see
 * {@see BqsRowKey}, which is the only thing that should ever compute one.
 *
 * The `$guarded = []` here rather than a `#[Fillable]` list is deliberate: there are
 * 61 columns, every one of them written by exactly one caller
 * ({@see BqsImportService}) from a DTO the reader built,
 * and no form ever posts to this table. Enumerating them would be a list that exists
 * only to be kept in step with the migration.
 *
 * @property int $id
 * @property int $bqs_sheet_id
 * @property int $line_no
 * @property string $row_key
 * @property string|null $vendor_style_no
 * @property string|null $pantone_colour
 * @property string|null $colour_variant
 * @property string|null $item_description
 * @property int|null $total_buy_units_store
 * @property Carbon|null $wm_wk_in_store_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BqsSheet $sheet
 * @property-read Collection<int, BqsRowMonth> $months
 * @property-read Collection<int, BqsRowPackSize> $packSizes
 */
class BqsRow extends Model implements Auditable
{
    /** @use HasFactory<BqsRowFactory> */
    use Audited, HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The BQS revision this line belongs to — and the buyer scope it inherits.
     *
     * @return BelongsTo<BqsSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(BqsSheet::class, 'bqs_sheet_id');
    }

    /**
     * The purchase-order lines that ordered against this plan.
     *
     * Empty is a normal state — a plan not yet ordered — and so is a line with no row,
     * which is why this is a plain `hasMany` on a nullable key rather than anything
     * that implies the two must correspond.
     *
     * @return HasMany<PoLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(PoLineItem::class, 'bqs_row_id');
    }

    /**
     * The numeric PO type this row's *initial* order is expected to carry.
     *
     * The BQS states it as `43 Import` and `purchase_orders.po_type` stores `43`, so
     * the mapping between a purchase order and the half of the plan it satisfies comes
     * out of the BQS row itself — nothing is hard-coded, and a buyer using different
     * codes needs no change here.
     */
    public function initialPoTypeCode(): ?int
    {
        return $this->poTypeCode($this->initial_po_type);
    }

    /**
     * The numeric PO type this row's *replenishment* orders are expected to carry.
     */
    public function replenPoTypeCode(): ?int
    {
        return $this->poTypeCode($this->replen_po_type);
    }

    private function poTypeCode(?string $value): ?int
    {
        return preg_match('/^\s*(\d+)/', (string) $value, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * The monthly DC intake for this line, in the buyer's own month order.
     *
     * @return HasMany<BqsRowMonth, $this>
     */
    public function months(): HasMany
    {
        return $this->hasMany(BqsRowMonth::class)->orderBy('month');
    }

    /**
     * The break-pack and case-pack size quantities, in the buyer's column order.
     *
     * @return HasMany<BqsRowPackSize, $this>
     */
    public function packSizes(): HasMany
    {
        return $this->hasMany(BqsRowPackSize::class)->orderBy('pack_type')->orderBy('size_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wm_wk_in_store_date' => 'date',
        ];
    }
}
