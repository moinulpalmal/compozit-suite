<?php

namespace Database\Factories\Settings;

use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplate;
use App\Models\Settings\TnaTemplateColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TnaTemplateColor>
 */
class TnaTemplateColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default rung is the **catch-all**, because a template with only one rung
     * should colour every date rather than none — a numbered default would leave
     * distant dates uncoloured and make a half-configured template look broken.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tna_template_id' => TnaTemplate::factory(),
            'notification_color_id' => NotificationColor::factory(),
            'max_days_remaining' => null,
        ];
    }

    /**
     * Bound this rung at a given number of days remaining, inclusive.
     *
     * Negative values are meaningful and intended: `-1` is "the date has passed".
     */
    public function upTo(int $maxDaysRemaining): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_days_remaining' => $maxDaysRemaining,
        ]);
    }
}
