<?php

namespace App\Http\Controllers\Merchandising;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\BqsLinkRequest;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\PurchaseOrder;
use App\Services\Merchandising\BqsPoLinker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Records which BQS row a purchase-order colour belongs to, when the documents did not
 * say so themselves.
 *
 * Colour matching is strict equality and Walmart truncates the colour column to fifteen
 * characters, so `BALLAD BLUE` arrives as `LTBLUE-BALLAD B` and never matches. About
 * half of every order therefore reaches a person — this is where they answer.
 *
 * **One idempotent endpoint, not a create and a delete.** A null `bqs_row_id` clears
 * the link, so linking and unlinking are the same request and the same code path all
 * the way down to {@see BqsPoLinker::link()}.
 *
 * **The decision is remembered as a rule**, not written onto these particular lines —
 * so the next order carrying the same colour needs no second visit here.
 */
class BqsLinkController extends Controller
{
    public function __construct(protected BqsPoLinker $linker) {}

    /**
     * Link, or unlink, one colour of one purchase order.
     */
    public function update(BqsLinkRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $row = $request->row();

        $this->linker->link(
            $purchaseOrder,
            $request->string('vendor_stock')->value(),
            $request->string('color')->value(),
            $row,
        );

        Inertia::flash('toast', $this->toastFor($row));

        return back();
    }

    /**
     * Describe what the decision did.
     *
     * Both outcomes are `success` (ARCHITECTURE.md §8.8): clearing a link is a
     * deliberate correction that worked, not a refusal. The message says the decision
     * will be reused, because that is the part a user cannot see and would otherwise
     * be surprised by on the next import.
     *
     * @return array{type: string, message: string}
     */
    private function toastFor(?BqsRow $row): array
    {
        if (! $row instanceof BqsRow) {
            return [
                'type' => 'success',
                'message' => __('Link removed. This colour will be left unlinked on future orders too.'),
            ];
        }

        return [
            'type' => 'success',
            'message' => __('Linked to :colour. Future orders with this colour will link to it automatically.', [
                'colour' => $row->pantone_colour ?? __('that BQS row'),
            ]),
        ];
    }
}
