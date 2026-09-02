<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\TnaPlanDto;
use App\Enums\Merchandising\TnaMilestone;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out when each milestone of a purchase order is due.
 *
 * **The only place TNA arithmetic lives.** Nothing else may compute a lead time or
 * pick an urgency colour — a second implementation would drift from this one, and a
 * schedule that disagrees with itself is worse than no schedule.
 *
 * The chain is short and every link can fail for a reason worth naming:
 *
 * ```
 * purchase order → its linked BQS rows → one BQS sheet → its bqs_date
 *                                                            ↓
 *            vendor_ship_date − bqs_date = lead time → the template covering it
 *                                                            ↓
 *                            bqs_date + each offset = the planned dates
 * ```
 *
 * Every failure produces a {@see TnaPlanDto} carrying a `reason`, never a silently
 * empty row — see that class for why.
 *
 * ## Dates are computed, never stored
 *
 * There is no TNA table. A plan is derived on every read, so correcting a template
 * corrects every order at once and there is nothing to backfill or recalculate. The
 * trade is deliberate and has one consequence worth stating: **editing a template
 * rewrites the past too.** A schedule printed last week is not reproducible from the
 * data. That is right for a proof of concept and wrong for a system of record;
 * capturing actual dates later is what will force plans to be stored.
 */
class TnaCalculator
{
    /**
     * The schedule for a single purchase order.
     */
    public function plan(PurchaseOrder $order): TnaPlanDto
    {
        return $this->plans(new EloquentCollection([$order]))[$order->id];
    }

    /**
     * The schedule for every given order, keyed by order id.
     *
     * **The cost does not grow with the page**, which is why the list page calls this
     * rather than {@see self::plan()} in a loop: the BQS dates come back in one
     * grouped query and the register is loaded once and reused, so twenty-five orders
     * cost exactly what one does. There is a test pinning that ratio.
     *
     * @param  EloquentCollection<int, PurchaseOrder>  $orders
     * @return array<int, TnaPlanDto>
     */
    public function plans(EloquentCollection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $bqsDates = $this->bqsDatesFor($orders->pluck('id')->all());
        $templates = $this->activeTemplates();
        $today = CarbonImmutable::today();

        $plans = [];

        foreach ($orders as $order) {
            $plans[$order->id] = $this->planFor($order, $bqsDates[$order->id] ?? [], $templates, $today);
        }

        return $plans;
    }

    /**
     * Every active template, ordered by band, with its milestones and colours loaded.
     *
     * @return EloquentCollection<int, TnaTemplate>
     */
    public function activeTemplates(): EloquentCollection
    {
        return TnaTemplate::query()
            ->active()
            ->with(['milestones', 'colors.color'])
            ->orderBy('lead_time_from')
            ->get();
    }

    /**
     * The active template whose band contains a lead time, if any.
     */
    public function templateFor(int $leadTimeDays): ?TnaTemplate
    {
        return TnaTemplate::query()
            ->covering($leadTimeDays)
            ->with(['milestones', 'colors.color'])
            ->first();
    }

    /**
     * The colour a date this many days away is drawn in.
     *
     * The ladder arrives ordered by {@see TnaTemplate::colors()} — ascending bound
     * with the catch-all last — so the first rung that covers the value is the
     * answer. Returns null when a template has no rung covering it, which happens
     * only when the ladder has no catch-all; the cell then renders uncoloured rather
     * than guessing at a severity nobody configured.
     */
    public function colorFor(TnaTemplate $template, int $daysRemaining): ?NotificationColor
    {
        foreach ($template->colors as $band) {
            if ($band->covers($daysRemaining)) {
                return $band->color;
            }
        }

        return null;
    }

    /**
     * Assemble one order's plan from the pre-loaded parts.
     *
     * @param  list<array{sheet_id: int, bqs_date: string}>  $sheets
     * @param  EloquentCollection<int, TnaTemplate>  $templates
     */
    private function planFor(
        PurchaseOrder $order,
        array $sheets,
        EloquentCollection $templates,
        CarbonImmutable $today,
    ): TnaPlanDto {
        $shipDate = $order->vendor_ship_date === null
            ? null
            : CarbonImmutable::parse($order->vendor_ship_date)->startOfDay();

        /*
         * More than one BQS behind one order is refused rather than resolved. Picking
         * the earliest would produce a lead time that belongs to no document, and the
         * merchandiser is the only one who can say which plan the order was placed
         * against — the same posture BqsImportService::collidingSheet() takes.
         */
        if (count($sheets) > 1) {
            return new TnaPlanDto(
                bqsDate: null,
                shipDate: $shipDate,
                leadTimeDays: null,
                template: null,
                milestones: $this->shipmentOnly($shipDate, $today),
                reason: __('This order is linked to :count different BQS sheets, so its BQS date is ambiguous.', [
                    'count' => count($sheets),
                ]),
            );
        }

        if ($sheets === []) {
            return new TnaPlanDto(
                bqsDate: null,
                shipDate: $shipDate,
                leadTimeDays: null,
                template: null,
                milestones: $this->shipmentOnly($shipDate, $today),
                reason: __('No colour on this order is linked to a BQS row, so there is no BQS date to schedule from.'),
            );
        }

        $bqsDate = CarbonImmutable::parse($sheets[0]['bqs_date'])->startOfDay();

        if ($shipDate === null) {
            return new TnaPlanDto(
                bqsDate: $bqsDate,
                shipDate: null,
                leadTimeDays: null,
                template: null,
                milestones: [],
                reason: __('This order has no vendor ship date, so its lead time cannot be measured.'),
            );
        }

        $leadTimeDays = (int) $bqsDate->diffInDays($shipDate, false);

        /*
         * A ship date on or before the BQS date is a data error, not a zero-day
         * programme: no band can sensibly cover it and pretending otherwise would
         * schedule milestones after the shipment they precede.
         */
        if ($leadTimeDays <= 0) {
            return new TnaPlanDto(
                bqsDate: $bqsDate,
                shipDate: $shipDate,
                leadTimeDays: $leadTimeDays,
                template: null,
                milestones: $this->shipmentOnly($shipDate, $today),
                reason: __('The ship date is not after the BQS date, so the lead time of :days days cannot be scheduled.', [
                    'days' => $leadTimeDays,
                ]),
            );
        }

        $template = $templates->first(
            fn (TnaTemplate $candidate): bool => $leadTimeDays >= $candidate->lead_time_from
                && $leadTimeDays <= $candidate->lead_time_to,
        );

        if ($template === null) {
            return new TnaPlanDto(
                bqsDate: $bqsDate,
                shipDate: $shipDate,
                leadTimeDays: $leadTimeDays,
                template: null,
                milestones: $this->shipmentOnly($shipDate, $today),
                reason: __('No active TNA template covers a lead time of :days days.', ['days' => $leadTimeDays]),
            );
        }

        return new TnaPlanDto(
            bqsDate: $bqsDate,
            shipDate: $shipDate,
            leadTimeDays: $leadTimeDays,
            template: $template,
            milestones: $this->milestonesFor($template, $bqsDate, $shipDate, $today),
        );
    }

    /**
     * Each milestone's date, how far off it is, and the colour that makes it.
     *
     * @return list<array{milestone: string, label: string, date: string|null, days_remaining: int|null, color: array{name: string, color_code: string}|null}>
     */
    private function milestonesFor(
        TnaTemplate $template,
        CarbonImmutable $bqsDate,
        CarbonImmutable $shipDate,
        CarbonImmutable $today,
    ): array {
        $milestones = [];

        foreach (TnaMilestone::cases() as $milestone) {
            $date = $milestone->offsetFromBqs()
                ? $this->plannedDate($template, $milestone, $bqsDate)
                : $shipDate;

            /*
             * A template need not schedule every milestone. One that omits trims
             * approval renders that column empty for its orders rather than
             * inventing an offset of zero, which would read as "due on the BQS date".
             */
            if ($date === null) {
                $milestones[] = $this->cell($milestone, null, null, null);

                continue;
            }

            $daysRemaining = (int) $today->diffInDays($date, false);

            $milestones[] = $this->cell(
                $milestone,
                $date,
                $daysRemaining,
                $this->colorFor($template, $daysRemaining),
            );
        }

        return $milestones;
    }

    /**
     * The date a template puts a planned milestone on, if it schedules it at all.
     */
    private function plannedDate(TnaTemplate $template, TnaMilestone $milestone, CarbonImmutable $bqsDate): ?CarbonImmutable
    {
        $offset = $template->offsetFor($milestone);

        return $offset === null ? null : $bqsDate->addDays($offset);
    }

    /**
     * The shipment cell alone, for an order that has a ship date but no schedule.
     *
     * The date is known from the order itself even when nothing else is, and showing
     * it uncoloured is more useful than showing a wholly empty row — but it is
     * deliberately uncoloured, because urgency is a template's judgement and there is
     * no template here.
     *
     * @return list<array{milestone: string, label: string, date: string|null, days_remaining: int|null, color: array{name: string, color_code: string}|null}>
     */
    private function shipmentOnly(?CarbonImmutable $shipDate, CarbonImmutable $today): array
    {
        if ($shipDate === null) {
            return [];
        }

        return [$this->cell(
            TnaMilestone::Shipment,
            $shipDate,
            (int) $today->diffInDays($shipDate, false),
            null,
        )];
    }

    /**
     * One rendered milestone cell.
     *
     * @return array{milestone: string, label: string, date: string|null, days_remaining: int|null, color: array{name: string, color_code: string}|null}
     */
    private function cell(
        TnaMilestone $milestone,
        ?CarbonImmutable $date,
        ?int $daysRemaining,
        ?NotificationColor $color,
    ): array {
        return [
            'milestone' => $milestone->value,
            'label' => $milestone->label(),
            'date' => $date?->toDateString(),
            'days_remaining' => $daysRemaining,
            'color' => $color === null ? null : [
                'name' => $color->name,
                'color_code' => $color->color_code,
            ],
        ];
    }

    /**
     * The distinct BQS sheets each order reaches through its linked colours.
     *
     * One grouped query for the whole page. The link is the authority and is not
     * filtered further: {@see BqsPoLinker} is its only writer and only ever links
     * rows on a current, usable sheet, carrying them forward on revision — so a
     * `current()` filter here could only ever hide a link that exists.
     *
     * @param  list<int>  $orderIds
     * @return array<int, list<array{sheet_id: int, bqs_date: string}>>
     */
    private function bqsDatesFor(array $orderIds): array
    {
        /** @var Collection<int, object{purchase_order_id: int, sheet_id: int, bqs_date: string}> $rows */
        $rows = DB::table('po_line_items')
            ->join('bqs_rows', 'bqs_rows.id', '=', 'po_line_items.bqs_row_id')
            ->join('bqs_sheets', 'bqs_sheets.id', '=', 'bqs_rows.bqs_sheet_id')
            ->whereIn('po_line_items.purchase_order_id', $orderIds)
            ->whereNotNull('po_line_items.bqs_row_id')
            ->distinct()
            ->get([
                'po_line_items.purchase_order_id',
                'bqs_sheets.id as sheet_id',
                'bqs_sheets.bqs_date',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->purchase_order_id][] = [
                'sheet_id' => (int) $row->sheet_id,
                'bqs_date' => (string) $row->bqs_date,
            ];
        }

        return $grouped;
    }
}
