<?php

namespace App\DataTransferObjects\Merchandising;

use App\Enums\Merchandising\TnaMilestone;
use App\Models\Settings\TnaTemplate;
use App\Services\Merchandising\TnaCalculator;
use Carbon\CarbonImmutable;

/**
 * One purchase order's schedule, or the reason it does not have one.
 *
 * **The reason is the point.** A TNA row with three blank cells is indistinguishable
 * from a row nobody has filled in yet, and the two need entirely different actions:
 * an unlinked order needs a colour linking on the purchase-order page, an uncovered
 * lead time needs a band adding to the register in Settings. Every path that produces
 * no dates therefore names itself in {@see self::$reason}, and the page prints it.
 *
 * Built only by {@see TnaCalculator}.
 */
final readonly class TnaPlanDto
{
    /**
     * @param  list<array{milestone: string, label: string, date: string|null, days_remaining: int|null, color: array{name: string, color_code: string}|null}>  $milestones
     */
    public function __construct(
        public ?CarbonImmutable $bqsDate,
        public ?CarbonImmutable $shipDate,
        public ?int $leadTimeDays,
        public ?TnaTemplate $template,
        public array $milestones = [],
        public ?string $reason = null,
    ) {}

    /**
     * Whether this order has a schedule at all.
     */
    public function isScheduled(): bool
    {
        return $this->template !== null;
    }

    /**
     * The shape the TNA page renders.
     *
     * `milestones` is a list rather than a map keyed by {@see TnaMilestone} so the
     * front end draws whatever columns the server sends, in the order it sends them.
     * Adding the twenty-sixth milestone then changes no TypeScript.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bqs_date' => $this->bqsDate?->toDateString(),
            'ship_date' => $this->shipDate?->toDateString(),
            'lead_time_days' => $this->leadTimeDays,
            'template' => $this->template === null ? null : [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'lead_time_from' => $this->template->lead_time_from,
                'lead_time_to' => $this->template->lead_time_to,
            ],
            'milestones' => $this->milestones,
            'reason' => $this->reason,
        ];
    }
}
