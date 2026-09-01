<?php

namespace Database\Factories\Settings;

use App\Enums\Merchandising\TnaMilestone;
use App\Models\Settings\TnaTemplate;
use App\Models\Settings\TnaTemplateMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TnaTemplateMilestone>
 */
class TnaTemplateMilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The milestone defaults to a *planned* case rather than a random one, because
     * `Shipment` is not schedulable and a factory that produced it by chance would
     * make an intermittently invalid row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tna_template_id' => TnaTemplate::factory(),
            'milestone' => TnaMilestone::TrimsApproval,
            'offset_days' => fake()->numberBetween(1, 60),
        ];
    }

    /**
     * Schedule a specific milestone at a specific offset from the BQS date.
     */
    public function schedules(TnaMilestone $milestone, int $offsetDays): static
    {
        return $this->state(fn (array $attributes): array => [
            'milestone' => $milestone,
            'offset_days' => $offsetDays,
        ]);
    }
}
