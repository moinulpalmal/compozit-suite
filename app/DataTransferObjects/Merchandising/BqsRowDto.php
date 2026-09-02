<?php

namespace App\DataTransferObjects\Merchandising;

use App\Enums\Merchandising\BqsPackType;
use App\Models\Merchandising\BqsRow;
use App\Services\Merchandising\BqsRowKey;

/**
 * One line of a BQS workbook, as the reader made it.
 *
 * The shape a row has *before* anything decides which parts become columns — which is
 * exactly what ARCHITECTURE.md §6.7 says a DTO is for. `values` holds the 61 static
 * fields keyed by their `bqs_rows` column name; `months` and `packSizes` hold the two
 * dynamic bands that become child rows rather than columns.
 *
 * `final readonly` with promoted properties, per §6.7.
 *
 * Unlike `PurchaseOrderDto`, this is **not** stored anywhere — a BQS row becomes real
 * columns on {@see BqsRow}, so `toArray()` here is a write payload, not a persisted
 * contract that a front-end also reads. Changing a key is an ordinary refactor.
 */
final readonly class BqsRowDto
{
    /**
     * @param  array<string, scalar|null>  $values  the 61 static fields, keyed by column name
     * @param  list<array{month: string, month_label: string, dc_units: int|null}>  $months
     * @param  list<array{pack_type: BqsPackType, size_label: string, size_order: int, quantity: int|null}>  $packSizes
     */
    public function __construct(
        public int $lineNo,
        public array $values,
        public array $months,
        public array $packSizes,
    ) {}

    /**
     * The row's identity — see {@see BqsRowKey} for why a BQS needs one at all.
     */
    public function key(): string
    {
        return BqsRowKey::for($this->values);
    }

    /**
     * How this row reads in a message to the uploader.
     *
     * Used when a duplicate has to be reported: a line number alone tells someone
     * nothing about which garment is wrong.
     */
    public function describe(): string
    {
        $parts = array_filter([
            $this->values['vendor_style_no'] ?? null,
            $this->values['pantone_colour'] ?? null,
        ], static fn (mixed $part): bool => is_scalar($part) && (string) $part !== '');

        return $parts === [] ? __('row :line', ['line' => $this->lineNo]) : implode(' / ', $parts);
    }

    /**
     * The static fields as a `bqs_rows` insert payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->values,
            'line_no' => $this->lineNo,
            'row_key' => $this->key(),
        ];
    }
}
