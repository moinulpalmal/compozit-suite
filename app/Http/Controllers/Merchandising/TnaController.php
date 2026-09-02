<?php

namespace App\Http\Controllers\Merchandising;

use App\Enums\Merchandising\TnaMilestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\TnaIndexRequest;
use App\Models\Merchandising\PurchaseOrder;
use App\Services\Merchandising\TnaCalculator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The time-and-action view: every live purchase order and when its milestones fall.
 *
 * **There is no policy and no writing.** `PurchaseOrder` is `BuyerScoped`, so orders
 * outside the actor's buyer access never reach the query (ARCHITECTURE.md §9.2), and
 * `merchandising.tna.view` on the route is the rest of the authorization story.
 * Nothing on this page is editable: the dates come from a template in Settings and
 * the ship date comes from the order, so every correction is made where the data
 * lives rather than here.
 */
class TnaController extends Controller
{
    public function __construct(protected TnaCalculator $calculator) {}

    /**
     * List the orders in force, each with its schedule or the reason it has none.
     *
     * **Current, usable orders only**, with no view switch. A superseded revision is
     * not a thing anyone is working towards, and a failed parse is known to be wrong;
     * either on a schedule board would be a deadline nobody owes.
     *
     * The plans are computed for the whole page in one call rather than per row —
     * {@see TnaCalculator::plans()} costs the same whatever the page size, where a
     * loop over {@see TnaCalculator::plan()} would multiply that by the row count.
     */
    public function index(TnaIndexRequest $request): Response
    {
        $filters = $request->filters();

        $orders = PurchaseOrder::query()
            ->current()
            ->usable()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->with('buyer:id,name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $plans = $this->calculator->plans($orders->getCollection());

        $rows = $orders->through(fn (PurchaseOrder $order): array => [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'buyer' => $order->buyer->name,
            'vendor_name' => $order->vendor_name,
            'factory_name' => $order->factory_name,
            'total_qty' => $order->total_qty,
            'parse_status' => $order->parse_status->value,
            'tna' => $plans[$order->id]->toArray(),
        ]);

        return Inertia::render('merchandising/tna/index', [
            'orders' => $rows,
            /*
             * The column set is sent by the server rather than hard-coded in the
             * page, so the twenty-sixth milestone is an enum case and a template row
             * — no TypeScript. It is the same list `TnaPlanDto` emits per row, in the
             * same order.
             */
            'milestones' => TnaMilestone::options(),
            'sortable' => PurchaseOrder::SORTABLE,
            'filterable' => PurchaseOrder::FILTERABLE,
            'perPageOptions' => TnaIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }
}
