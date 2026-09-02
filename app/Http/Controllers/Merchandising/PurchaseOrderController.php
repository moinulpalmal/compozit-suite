<?php

namespace App\Http\Controllers\Merchandising;

use App\Enums\Merchandising\PoConflictDecision;
use App\Enums\Merchandising\PoParseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\PurchaseOrderIndexRequest;
use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use App\Services\Merchandising\BqsPoLinker;
use App\Services\Merchandising\PurchaseOrderImportService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads imported purchase orders.
 *
 * **There is no policy.** `PurchaseOrder` is `BuyerScoped`, so an order outside the
 * signed-in user's buyer access is invisible to route-model binding and 404s before
 * anything is authorized — record-level access is already decided by
 * ARCHITECTURE.md §9.2. The `merchandising.purchase-orders.view` middleware on the
 * routes is the rest of the authorization story, and a policy returning `true` would
 * only obscure that.
 *
 * Writing is not here: an order arrives through
 * {@see PurchaseOrderImportController} and is never edited by hand.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderImportService $imports,
        protected BqsPoLinker $linker,
    ) {}

    /**
     * List imported purchase orders.
     *
     * This page also hosts the import dialog, so it carries what that dialog needs:
     * the buyers the actor may import for, and any import of theirs still waiting on a
     * decision. **Both are gated on the import permission** — a `production-manager`
     * reads this list and can never import, so they pay for neither query.
     *
     * They are sent eagerly rather than through `Inertia::optional()`: a user's buyer
     * set is short by construction, and one `pluck` costs less than a round trip plus
     * a loading state inside the modal.
     */
    public function index(PurchaseOrderIndexRequest $request): Response
    {
        $filters = $request->filters();

        $query = PurchaseOrder::query()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction']);

        /*
         * `view` chooses the record set, which is why it is a toolbar control rather
         * than a filter cell (ARCHITECTURE.md §8.6). The default hides superseded
         * revisions *and* failed parses, so the list a merchandiser opens is the
         * orders that are actually in force.
         */
        match ($request->view()) {
            PurchaseOrderIndexRequest::VIEW_REVISIONS => $query->usable(),
            PurchaseOrderIndexRequest::VIEW_FAILED => $query->where('parse_status', PoParseStatus::Failed->value),
            default => $query->current()->usable(),
        };

        $purchaseOrders = $query
            ->with('buyer:id,name')
            ->withCount('lineItems')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'revision_no' => $order->revision_no,
                'is_current' => $order->is_current,
                'buyer' => $order->buyer->name,
                'vendor_name' => $order->vendor_name,
                'factory_name' => $order->factory_name,
                'document_status' => $order->document_status,
                'currency' => $order->currency,
                'total_qty' => $order->total_qty,
                'total_cartons' => $order->total_cartons,
                'vendor_ship_date' => $order->vendor_ship_date?->toDateString(),
                'cancel_date' => $order->cancel_date?->toDateString(),
                'revised_at' => $order->revised_at?->toDateTimeString(),
                'parse_status' => $order->parse_status->value,
                'confidence' => (float) $order->confidence,
                'line_items_count' => $order->line_items_count,
            ]);

        $canImport = (bool) $request->user()?->can('merchandising.purchase-orders.import');

        return Inertia::render('merchandising/purchase-orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'importBuyers' => $canImport ? $this->imports->assignableBuyerOptions() : [],
            'pendingImport' => $canImport ? $this->imports->pendingFor($request->user()) : null,
            'acceptedExtensions' => config('po-parser.accepted_extensions'),
            'maxFileSizeKb' => (int) config('po-parser.limits.max_file_size_kb'),
            'conflictDecisions' => PoConflictDecision::options(),
            'parseStatuses' => PoParseStatus::options(),
            'views' => PurchaseOrderIndexRequest::VIEWS,
            'sortable' => PurchaseOrder::SORTABLE,
            'filterable' => PurchaseOrder::FILTERABLE,
            'perPageOptions' => PurchaseOrderIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Show one purchase order in full.
     *
     * **Not paginated.** The packs and their line items render as a tree, and
     * ARCHITECTURE.md §8.6 records that grouped rendering and pagination are
     * incompatible — a page boundary would cut a pack in half. The bound is the
     * document: `po-parser.limits` caps what can be imported in the first place.
     */
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'buyer:id,name',
            'import:id,source_file_name,detected_file_type,created_at',
            'lineItems.bqsRow:id,bqs_sheet_id,pantone_colour,colour_family',
        ]);

        return Inertia::render('merchandising/purchase-orders/show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'revision_no' => $purchaseOrder->revision_no,
                'is_current' => $purchaseOrder->is_current,
                'revised_at' => $purchaseOrder->revised_at?->toDateTimeString(),
                'revised_by' => $purchaseOrder->revised_by,
                'buyer' => $purchaseOrder->buyer->name,
                'document_status' => $purchaseOrder->document_status,
                'quote_id' => $purchaseOrder->quote_id,
                'po_type' => $purchaseOrder->po_type?->label(),
                'currency' => $purchaseOrder->currency,
                'exchange_rate' => $purchaseOrder->exchange_rate,
                'vendor_name' => $purchaseOrder->vendor_name,
                'factory_id' => $purchaseOrder->factory_id,
                'factory_name' => $purchaseOrder->factory_name,
                'total_cartons' => $purchaseOrder->total_cartons,
                'total_qty' => $purchaseOrder->total_qty,
                'vendor_ship_date' => $purchaseOrder->vendor_ship_date?->toDateString(),
                'cancel_date' => $purchaseOrder->cancel_date?->toDateString(),
                'parse_status' => $purchaseOrder->parse_status->value,
                'confidence' => (float) $purchaseOrder->confidence,
                'template_fingerprint' => $purchaseOrder->template_fingerprint,
                'source_file_name' => $purchaseOrder->import->source_file_name,
                'imported_at' => $purchaseOrder->import->created_at?->toDateTimeString(),
                /* Addresses, logistics, tariffs, comments and the packs in full. */
                'payload' => $purchaseOrder->payload,
            ],
            'colourLinks' => $this->colourLinks($purchaseOrder),
            'canLink' => (bool) request()->user()?->can('merchandising.purchase-orders.update'),
        ]);
    }

    /**
     * One entry per colour on this order: what it is linked to, and what it could be.
     *
     * **Grouped by colour, not by line.** A pack is one colour in five sizes, and all
     * five always belong to the same plan row — so the decision is asked once per
     * colour rather than sixty times per document.
     *
     * Candidates are restricted to the same vendor style and the same buyer, so a
     * colour with no plan behind it (`TEAL-ICY MORN` on the reference document) is
     * offered nothing that would manufacture a false link.
     *
     * @return list<array<string, mixed>>
     */
    private function colourLinks(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->lineItems
            ->groupBy(fn (PoLineItem $line): string => $line->vendor_stock.'|'.$line->color)
            ->map(function ($lines, string $key) use ($purchaseOrder): array {
                /** @var PoLineItem $first */
                $first = $lines->first();
                $row = $first->bqsRow;

                return [
                    'key' => $key,
                    'vendor_stock' => $first->vendor_stock,
                    'color' => $first->color,
                    'bqs_row_id' => $row?->id,
                    'bqs_sheet_id' => $row?->bqs_sheet_id,
                    'label' => $row === null
                        ? null
                        : trim("{$row->pantone_colour} ({$row->colour_family})"),
                    'source' => $first->bqs_link_source?->value,
                    'ordered_units' => $lines->sum(
                        fn (PoLineItem $line): int => (int) $line->orderedUnits()
                    ),
                    'candidates' => $this->linker->candidatesFor(
                        $purchaseOrder,
                        (string) $first->vendor_stock,
                        $first->color,
                    ),
                ];
            })
            ->values()
            ->all();
    }
}
