<?php

namespace Database\Factories\Merchandising;

use App\Models\Merchandising\PoLineItem;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoLineItem>
 */
class PoLineItemFactory extends Factory
{
    protected $model = PoLineItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'pack_number' => $this->faker->numberBetween(1, 4),
            'pack_description' => $this->faker->words(3, true),
            'assortment_id' => (string) $this->faker->numberBetween(1000000, 9999999),
            'vendor_stock' => $this->faker->bothify('??########'),
            'color' => $this->faker->safeColorName(),
            'size' => $this->faker->randomElement(['XS-4-5', 'S(6)', 'M(7/8)', 'L(10-12)', 'XL(14-16)']),
            'quantity' => $this->faker->numberBetween(1, 5000),
            'item_number' => (string) $this->faker->numberBetween(100000000, 999999999),
            'vendor_stock_number' => $this->faker->bothify('??########'),
            'mfg_stock_number' => $this->faker->bothify('??######'),
            'product_number' => (string) $this->faker->numerify('#############'),
            'upc_number' => (string) $this->faker->numerify('#############'),
            'item_description1' => $this->faker->words(3, true),
            'item_description2' => $this->faker->words(2, true),
            'upc_description' => $this->faker->words(3, true),
            'signing_description' => $this->faker->words(2, true),
            'uom_qty' => 1,
            'uom_code' => 'EA',
        ];
    }
}
