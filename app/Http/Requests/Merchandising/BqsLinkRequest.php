<?php

namespace App\Http\Requests\Merchandising;

use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a manual BQS link made on the purchase-order detail page.
 *
 * **The cross-buyer guard lives here and in the linker, and both are needed.** Neither
 * `po_line_items` nor `bqs_rows` carries a `buyer_id` — both reach it through a parent
 * (ARCHITECTURE.md §9.2) — so the database will happily accept a Walmart line pointing
 * at a George row. `bqs_row_id` is therefore validated against the rows of *this
 * order's* buyer, not against `bqs_rows` at large.
 *
 * The order itself is already safe: `PurchaseOrder` is `BuyerScoped`, so one outside
 * the actor's access 404s at route-model binding before this request runs.
 *
 * **A null `bqs_row_id` clears the link**, which is why the field is nullable rather
 * than required — unlinking is the same request with an empty value, and therefore the
 * same code path on the server.
 */
class BqsLinkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PurchaseOrder $order */
        $order = $this->route('purchaseOrder');

        return [
            'vendor_stock' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:100'],
            'bqs_row_id' => [
                'nullable',
                'integer',
                Rule::exists('bqs_rows', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'bqs_sheet_id',
                        BqsSheet::query()->withoutBuyerScope()->current()->usable()
                            ->where('buyer_id', $order->buyer_id)->select('id')
                    )
                ),
            ],
        ];
    }

    /**
     * The BQS row chosen, or null to clear the link.
     */
    public function row(): ?BqsRow
    {
        $id = $this->validated()['bqs_row_id'] ?? null;

        return $id === null ? null : BqsRow::query()->find($id);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bqs_row_id.exists' => __('That BQS row is not one you can link this order to.'),
            'vendor_stock.required' => __('Which style is being linked was not sent.'),
            'color.required' => __('Which colour is being linked was not sent.'),
        ];
    }
}
