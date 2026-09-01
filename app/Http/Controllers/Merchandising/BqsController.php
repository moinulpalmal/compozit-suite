<?php

namespace App\Http\Controllers\Merchandising;

use App\Enums\Merchandising\BqsConflictDecision;
use App\Enums\Merchandising\BqsParseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\BqsIndexRequest;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsRowMonth;
use App\Models\Merchandising\BqsRowPackSize;
use App\Models\Merchandising\BqsSheet;
use App\Services\Admin\BuyerService;
use App\Services\Merchandising\BqsImportService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads imported BQS records.
 *
 * **There is no policy.** `BqsSheet` is `BuyerScoped`, so a BQS outside the signed-in
 * user's buyer access is invisible to route-model binding and 404s before anything is
 * authorized — record-level access is already decided by ARCHITECTURE.md §9.2. The
 * `merchandising.bqs.view` middleware on the routes is the rest of the authorization
 * story, and a policy returning `true` would only obscure that.
 *
 * Writing is not here: a BQS arrives through {@see BqsImportController} and is never
 * edited by hand.
 */
class BqsController extends Controller
{
    public function __construct(
        protected BqsImportService $imports,
        protected BuyerService $buyers,
    ) {}

    /**
     * List imported BQS records.
     *
     * This page also hosts the import dialog, so it carries what that dialog needs:
     * the buyers the actor may import for, and any import of theirs still waiting on a
     * decision. **Both are gated on the import permission**, so a read-only role pays
     * for neither query.
     */
    public function index(BqsIndexRequest $request): Response
    {
        $filters = $request->filters();

        $query = BqsSheet::query()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction']);

        /*
         * `view` chooses the record set, which is why it is a toolbar control rather
         * than a filter cell (ARCHITECTURE.md §8.6). The default hides superseded
         * revisions, so the list a merchandiser opens is the BQS records in force.
         */
        match ($request->view()) {
            BqsIndexRequest::VIEW_REVISIONS => $query->usable(),
            default => $query->current()->usable(),
        };

        $sheets = $query
            ->with('buyer:id,name')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (BqsSheet $sheet): array => [
                'id' => $sheet->id,
                'title' => $sheet->title,
                'bqs_date' => $sheet->bqs_date->toDateString(),
                'buyer' => $sheet->buyer->name,
                'fye' => $sheet->fye,
                'season' => $sheet->season,
                'department' => $sheet->department,
                'revision_no' => $sheet->revision_no,
                'is_current' => $sheet->is_current,
                'row_count' => $sheet->row_count,
                'parse_status' => $sheet->parse_status->value,
            ]);

        $canImport = (bool) $request->user()?->can('merchandising.bqs.import');

        return Inertia::render('merchandising/bqs/index', [
            'sheets' => $sheets,
            'importBuyers' => $canImport ? $this->buyers->assignableOptions() : [],
            'pendingImport' => $canImport ? $this->imports->pendingFor($request->user()) : null,
            'maxFileSizeKb' => (int) config('bqs-import.limits.max_file_size_kb'),
            'conflictDecisions' => BqsConflictDecision::options(),
            'parseStatuses' => BqsParseStatus::options(),
            'views' => BqsIndexRequest::VIEWS,
            'sortable' => BqsSheet::SORTABLE,
            'filterable' => BqsSheet::FILTERABLE,
            'perPageOptions' => BqsIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Show one BQS in full.
     *
     * **Not paginated.** The months and pack sizes are pivoted back into the buyer's
     * own column order, which is grouped rendering — and ARCHITECTURE.md §8.6 records
     * that grouping and pagination are incompatible, because a page boundary cuts a
     * group in half. The bound is the workbook: `bqs-import.limits.max_rows` caps what
     * can be imported in the first place.
     *
     * The month and size columns are derived from the rows rather than declared,
     * because they are exactly the part of a BQS that is not schema — that is the
     * whole reason they are child rows.
     */
    public function show(BqsSheet $bqsSheet): Response
    {
        $bqsSheet->load([
            'buyer:id,name',
            'import:id,source_file_name,detected_file_type,sheet_name,header_fingerprint,created_at',
            'rows.months',
            'rows.packSizes',
            /*
             * The orders placed against each plan line. `purchaseOrder` is needed for
             * its `po_type`, which decides whether an order counts against the initial
             * buy or the replenishment — eager-loaded because otherwise this is one
             * query per line item on a page that renders every one of them.
             */
            'rows.lineItems.purchaseOrder:id,po_number,po_type,parse_status',
        ]);

        $monthColumns = $bqsSheet->rows
            ->flatMap(fn (BqsRow $row): iterable => $row->months)
            ->unique('month')
            ->sortBy('month')
            ->values()
            ->map(fn (BqsRowMonth $month): array => [
                'key' => $month->month->toDateString(),
                'label' => $month->month_label,
            ])
            ->all();

        $packColumns = $bqsSheet->rows
            ->flatMap(fn (BqsRow $row): iterable => $row->packSizes)
            ->unique(fn (BqsRowPackSize $pack): string => $pack->pack_type->value.'|'.$pack->size_label)
            /*
             * Band first, then the buyer's own column order — never the size label,
             * which sorts as text and would put XS after XL.
             */
            ->sortBy(fn (BqsRowPackSize $pack): string => sprintf(
                '%s|%03d', $pack->pack_type->value, $pack->size_order,
            ))
            ->values()
            ->map(fn (BqsRowPackSize $pack): array => [
                'key' => $pack->pack_type->value.'|'.$pack->size_label,
                'pack_type' => $pack->pack_type->value,
                'pack_label' => $pack->pack_type->label(),
                'label' => $pack->size_label,
            ])
            ->all();

        return Inertia::render('merchandising/bqs/show', [
            'sheet' => [
                'id' => $bqsSheet->id,
                'title' => $bqsSheet->title,
                'bqs_date' => $bqsSheet->bqs_date->toDateString(),
                'buyer' => $bqsSheet->buyer->name,
                'fye' => $bqsSheet->fye,
                'season' => $bqsSheet->season,
                'department' => $bqsSheet->department,
                'revision_no' => $bqsSheet->revision_no,
                'is_current' => $bqsSheet->is_current,
                'row_count' => $bqsSheet->row_count,
                'parse_status' => $bqsSheet->parse_status->value,
                'source_file_name' => $bqsSheet->import->source_file_name,
                'sheet_name' => $bqsSheet->import->sheet_name,
                'header_fingerprint' => $bqsSheet->import->header_fingerprint,
                'imported_at' => $bqsSheet->import->created_at?->toDateTimeString(),
                'warnings' => $bqsSheet->payload['warnings'] ?? [],
            ],
            'monthColumns' => $monthColumns,
            'packColumns' => $packColumns,
            'rows' => $bqsSheet->rows->map(fn (BqsRow $row): array => [
                'id' => $row->id,
                'line_no' => $row->line_no,
                'ordered' => $this->orderedAgainst($row),
                ...$row->only($this->rowFields()),
                'months' => $row->months
                    ->mapWithKeys(fn (BqsRowMonth $month): array => [
                        $month->month->toDateString() => $month->dc_units,
                    ])->all(),
                'pack_sizes' => $row->packSizes
                    ->mapWithKeys(fn (BqsRowPackSize $pack): array => [
                        $pack->pack_type->value.'|'.$pack->size_label => $pack->quantity,
                    ])->all(),
            ])->all(),
        ]);
    }

    /**
     * What has actually been ordered against one plan line.
     *
     * **Ordered units are `quantity × total_cartons_per_line`, never `quantity`.** The
     * line quantity is the size ratio inside one pack — the reference document's five
     * sizes sum to the fourteen of "14PC GR SS SKATER DRESS" — and the carton count is
     * how many packs were bought. {@see PoLineItem::orderedUnits()} owns that
     * arithmetic; summing `quantity` here would report 14 against a plan of 5,502.
     *
     * A purchase order counts against the initial buy or the replenishment by matching
     * its `po_type` to the codes **the BQS row itself states** (`43 Import`,
     * `42 Import Seasonal`), so nothing about Walmart's numbering is hard-coded. An
     * order matching neither is counted in `other` rather than silently dropped —
     * dropping it would make the totals disagree with the orders on screen.
     *
     * **The comparison is against the OMNI columns, and the documents are exact about
     * it.** Ecomm is ordered as its *own* purchase order, so the initial buy arrives as
     * two type-43 orders that sum to `Initial Set Units / OMNI`:
     *
     * ```text
     * PO ...001 (type 43)   5,502  = Initial Set Units / Store
     * PO ...002 (type 43)     266  = Initial Set Units / Ecomm
     *                      -------
     *                       5,768  = Initial Set Units / OMNI
     * PO ...003 (type 42)  21,868  = Replenishment Units / OMNI
     * ```
     *
     * Comparing against Store instead reports 105% for an initial buy that is exactly
     * complete. That mistake was made once here, from a single pack's carton count
     * read as if it applied to the whole order — the counts in fact range from 16 to
     * 1,562 across packs. Do not reintroduce it.
     *
     * @return array{initial: int, replen: int, other: int, po_numbers: list<string>}
     */
    private function orderedAgainst(BqsRow $row): array
    {
        $initialType = $row->initialPoTypeCode();
        $replenType = $row->replenPoTypeCode();

        $totals = ['initial' => 0, 'replen' => 0, 'other' => 0];
        $poNumbers = [];

        foreach ($row->lineItems as $line) {
            $order = $line->purchaseOrder;

            /* A failed parse is not order data — `PurchaseOrder::scopeUsable()`. */
            if (! $order->parse_status->isUsable()) {
                continue;
            }

            $units = $line->orderedUnits();

            if ($units === null) {
                continue;
            }

            $type = $order->po_type?->value ?? null;

            $bucket = match (true) {
                $initialType !== null && $type === $initialType => 'initial',
                $replenType !== null && $type === $replenType => 'replen',
                default => 'other',
            };

            $totals[$bucket] += $units;
            $poNumbers[$order->po_number] = true;
        }

        return [...$totals, 'po_numbers' => array_keys($poNumbers)];
    }

    /**
     * The row columns the detail table renders, in the workbook's own order.
     *
     * Listed rather than sent whole: `$row->toArray()` would ship `row_key`,
     * `bqs_sheet_id` and the timestamps to the browser for every row, none of which
     * the page draws.
     *
     * @return list<string>
     */
    private function rowFields(): array
    {
        return [
            'fye', 'season', 'department', 'buyer_merchant', 'item_status', 'quote_id',
            'category', 'sub_category', 'brand_id', 'fine_line', 'vendor_style_no',
            'item_description', 'pantone_colour', 'colour_family', 'colour_variant',
            'other_colour', 'first_cost', 'regular_cost', 'regular_retail',
            'regular_imu_pct', 'wm_wk_in_store', 'reg_wos', 'season_code',
            'on_floor_month', 'vendor_name', 'vendor_no', 'imp_dom', 'country_of_origin',
            'factory_id', 'factory_name', 'initial_po_type', 'replen_po_type',
            'reg_ecom_penetration_pct', 'total_stores',
            'initial_set_units_store', 'initial_set_units_ecomm', 'initial_set_units_omni',
            'extra_initial_packs',
            'total_buy_units_store', 'total_buy_units_ecomm', 'total_buy_units_omni',
            'replenishment_units_store', 'replenishment_units_ecomm', 'replenishment_units_omni',
            'first_cost_store', 'first_cost_ecomm', 'first_cost_omni',
            'landed_store_cost_store', 'landed_store_cost_ecomm', 'landed_store_cost_omni',
            'total_buy_dollar_store', 'total_buy_dollar_ecomm', 'total_buy_dollar_omni',
            'commodity_type', 'fixture_capacity', 'pack_ratio', 'pack_units',
            'replen_type', 'replen_pack', 'vndr_pack', 'whse_pack',
        ];
    }
}
