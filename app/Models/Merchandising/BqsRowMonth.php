<?php

namespace App\Models\Merchandising;

use App\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One month of a BQS row's `In DC Units` band.
 *
 * A row rather than a column because the band's headers are **data**: this workbook
 * runs `November-2026` to `April-2028`, and the next season's runs somewhere else.
 * See the `create_bqs_row_months_table` migration.
 *
 * `month_label` keeps the header verbatim so the detail page can render the buyer's
 * own wording; `month` is normalised to the first of the month so it sorts and groups.
 *
 * **No `buyer_id`** — it reaches its buyer through {@see BqsRow} → {@see BqsSheet},
 * which is scoped (ARCHITECTURE.md §9.2).
 *
 * @property int $id
 * @property int $bqs_row_id
 * @property Carbon $month
 * @property string $month_label
 * @property int|null $dc_units
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BqsRow $row
 */
class BqsRowMonth extends Model implements Auditable
{
    use Audited;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The BQS line this month belongs to.
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
            'month' => 'date',
        ];
    }
}
