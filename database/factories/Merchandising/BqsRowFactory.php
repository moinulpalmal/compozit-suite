<?php

namespace Database\Factories\Merchandising;

use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Services\Merchandising\BqsRowKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BqsRow>
 */
class BqsRowFactory extends Factory
{
    protected $model = BqsRow::class;

    /**
     * Define the model's default state.
     *
     * **`row_key` is computed, never faked.** It is the identity the whole revision
     * mechanism rests on ({@see BqsRowKey}), so a factory inventing a random one would
     * let a collision test pass while proving nothing about how collisions are
     * actually found.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identity = [
            'fye' => (string) $this->faker->numberBetween(2026, 2030),
            'season' => $this->faker->randomElement(['SS', 'AW']),
            'department' => 'GIRLSWEAR',
            'vendor_style_no' => strtoupper($this->faker->bothify('GRS#####?')),
            'pantone_colour' => $this->faker->unique()->colorName(),
            'colour_variant' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'item_description' => 'GR SS SKATER DRESS',
        ];

        return [
            'bqs_sheet_id' => BqsSheet::factory(),
            'line_no' => $this->faker->numberBetween(3, 500),
            'row_key' => BqsRowKey::for($identity),
            ...$identity,
            'first_cost' => $this->faker->randomFloat(4, 1, 20),
            'total_buy_units_store' => $this->faker->numberBetween(1000, 50000),
            'total_buy_units_ecomm' => $this->faker->numberBetween(100, 900),
        ];
    }

    /**
     * A row carrying a specific identity, so a collision can be set up deliberately.
     *
     * @param  array<string, string>  $identity
     */
    public function identifiedBy(array $identity): static
    {
        return $this->state(fn (array $attributes): array => [
            ...$identity,
            'row_key' => BqsRowKey::for([...$attributes, ...$identity]),
        ]);
    }
}
