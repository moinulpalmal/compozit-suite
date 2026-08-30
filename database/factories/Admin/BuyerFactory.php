<?php

namespace Database\Factories\Admin;

use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Buyer>
 */
class BuyerFactory extends Factory
{
    protected $model = Buyer::class;

    /**
     * Define the model's default state.
     *
     * `name` and `code` are both unique in the database, so both are drawn
     * uniquely here — a factory that collides on the hundredth row is a test
     * that fails for a reason nobody wants to debug.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * A buyer retired from the pickers, its history left in place.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecordStatus::Inactive,
        ]);
    }
}
