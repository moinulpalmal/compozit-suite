<?php

namespace Database\Factories\Settings;

use App\Enums\Merchandising\TnaMilestone;
use App\Enums\RecordStatus;
use App\Models\Settings\TnaTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TnaTemplate>
 */
class TnaTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default band is deliberately **narrow and low** — 1 to 30 days — so that a
     * template created without an explicit band cannot accidentally cover the
     * 263-day programmes the real fixtures produce. A factory whose default silently
     * matched the fixture data would make "no template covers this" untestable.
     *
     * `name` is drawn from faker's unique pool because the column is unique.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'lead_time_from' => 1,
            'lead_time_to' => 30,
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * Cover an explicit, inclusive lead-time band.
     */
    public function covering(int $from, int $to): static
    {
        return $this->state(fn (array $attributes): array => [
            'lead_time_from' => $from,
            'lead_time_to' => $to,
        ]);
    }

    /**
     * Attach the two proof-of-concept offsets, in days after the BQS date.
     *
     * `Shipment` is never given an offset — it is read from the purchase order.
     */
    public function withOffsets(int $trims = 10, int $productionSample = 12): static
    {
        return $this->afterCreating(function (TnaTemplate $template) use ($trims, $productionSample): void {
            $template->milestones()->createMany([
                ['milestone' => TnaMilestone::TrimsApproval, 'offset_days' => $trims],
                ['milestone' => TnaMilestone::ProductionSampleApproval, 'offset_days' => $productionSample],
            ]);
        });
    }

    /**
     * Indicate that the band has been retired, so nothing matches it.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecordStatus::Inactive,
        ]);
    }
}
