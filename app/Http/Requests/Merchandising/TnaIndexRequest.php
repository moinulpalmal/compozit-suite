<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\PoParseStatus;
use App\Http\Requests\ListRequest;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the TNA list's query string.
 *
 * The TNA page lists purchase orders — a row is an order, because every date on it
 * is an order-level fact — so it borrows that model's allow-lists wholesale rather
 * than declaring its own. There is no `Tna` model to hang them on and inventing one
 * would name a table that does not exist.
 *
 * **Lead time and the planned dates are absent from both lists, and cannot be added
 * here.** They are computed per row by `TnaCalculator` after the page has been
 * fetched, so the database cannot order or filter by them; sorting by lead time
 * would mean storing it, which is the trade the derived-on-read decision makes.
 * `vendor_ship_date` is the sortable column closest to it and is offered instead.
 */
class TnaIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return PurchaseOrder::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return PurchaseOrder::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'filter.parse_status' => ['nullable', Rule::enum(PoParseStatus::class)],
        ];
    }
}
