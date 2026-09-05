<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use App\Contracts\Auditable;
use App\Enums\Merchandising\BqsPackType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One size quantity from a BQS row's `Break Packs` or `Case Packs` band.
 *
 * A row rather than a column because the size labels are **data**: this workbook is
 * girls' dresses (`XS(4/5) … XL(14/16)`), and a shirt BQS carries S/M/L/XL/XXL. It is
 * also what keeps a BQS joinable to `po_line_items`, which stores size as rows for the
 * same reason. See the `create_bqs_row_pack_sizes_table` migration.
 *
 * `size_order` preserves the buyer's column sequence — a size label sorts
 * meaninglessly as text, and XS after XL is wrong in a way no reader would forgive.
 *
 * **No `buyer_id`** — it reaches its buyer through {@see BqsRow} → {@see BqsSheet},
 * which is scoped (ARCHITECTURE.md §9.2).
 *
 * @property int $id
 * @property int $bqs_row_id
 * @property BqsPackType $pack_type
 * @property string $size_label
 * @property int $size_order
 * @property int|null $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BqsRow $row
 */
class BqsRowPackSize extends Model implements Auditable
{
    use Audited;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The BQS line this size belongs to.
     *
     * @return BelongsTo<BqsRow, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(BqsRow::class, 'bqs_row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pack_type' => BqsPackType::class,
        ];
    }
}
