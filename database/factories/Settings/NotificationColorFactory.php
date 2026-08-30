<?php

namespace Database\Factories\Settings;

use App\Enums\RecordStatus;
use App\Models\Settings\NotificationColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationColor>
 */
class NotificationColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `name` and `color_code` are both unique in the database, so both are drawn
     * from faker's unique pool. The colour is uppercased here for the same
     * reason the write requests uppercase it: the stored form is the canonical
     * one, and a factory that produced lowercase rows would let a test pass
     * against data the application cannot itself create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color_code' => strtoupper(fake()->unique()->hexColor()),
            'retention_days' => fake()->numberBetween(1, 365),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * Indicate that the colour has been retired from the pickers.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecordStatus::Inactive,
        ]);
    }
}
