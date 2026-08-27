<?php

namespace Database\Factories\Admin;

use App\Enums\RecordStatus;
use App\Models\Admin\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `name` and `short_form` are both unique in the database, so both are
     * drawn from faker's unique pool.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'short_form' => strtoupper(fake()->unique()->lexify('???')),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * Indicate that the designation has been retired from the pickers.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecordStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the designation has no short form yet.
     *
     * The unique index allows repeated NULLs, so several may coexist.
     */
    public function withoutShortForm(): static
    {
        return $this->state(fn (array $attributes): array => [
            'short_form' => null,
        ]);
    }
}
