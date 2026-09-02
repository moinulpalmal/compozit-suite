<?php

namespace Database\Factories\Merchandising;

use App\Enums\Merchandising\PoParseStatus;
use App\Enums\Merchandising\PoType;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * The import is created **under the same buyer** as the order. Letting each
     * default independently would produce an order whose own import belongs to
     * someone else — invisible in most assertions and wrong in every buyer-scope test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'po_import_id' => fn (array $attributes): int => PoImport::factory()
                ->create(['buyer_id' => $attributes['buyer_id']])->id,

            'po_number' => $this->faker->unique()->numerify('58########'),
            'revision_no' => 1,
            'revised_at' => $this->faker->dateTimeBetween('-1 year'),
            'revised_by' => $this->faker->bothify('??####'),
            'source_hash' => hash('sha256', $this->faker->unique()->uuid()),
            'is_current' => true,

            'document_status' => 'ACTIVE',
            'quote_id' => (string) $this->faker->numberBetween(10000000, 99999999),
            'po_type' => PoType::WarehouseBulk,

            'create_date' => $this->faker->date(),
            'negotiation_date' => $this->faker->date(),
            'vendor_ship_date' => $this->faker->date(),
            'cancel_date' => $this->faker->date(),

            'currency' => 'USD',
            'exchange_rate' => $this->faker->randomFloat(5, 1, 2),
            'total_cartons' => $this->faker->numberBetween(10, 500),
            'total_qty' => $this->faker->numberBetween(100, 20000),
            'total_weight_kgs' => $this->faker->randomFloat(3, 10, 5000),
            'total_volume_cbm' => $this->faker->randomFloat(3, 1, 100),
            'net_first_cost_usd' => $this->faker->randomFloat(4, 1, 50),
            'net_first_cost_cnd' => $this->faker->randomFloat(4, 1, 50),

            'vendor_name' => $this->faker->company(),
            'factory_id' => (string) $this->faker->numberBetween(10000000, 99999999),
            'factory_name' => $this->faker->company(),

            'template_fingerprint' => substr(sha1('template'), 0, 12),
            'parse_status' => PoParseStatus::Success,
            'confidence' => 1.000,
            'payload' => [],
        ];
    }

    /**
     * An order the parser could not read cleanly — stored, but not to be relied on.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'parse_status' => PoParseStatus::Failed,
            'confidence' => 0.600,
        ]);
    }

    /**
     * A superseded revision.
     */
    public function superseded(): static
    {
        return $this->state(fn (): array => ['is_current' => false]);
    }
}
