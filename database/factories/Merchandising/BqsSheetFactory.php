<?php

namespace Database\Factories\Merchandising;

use App\Enums\Merchandising\BqsParseStatus;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BqsSheet>
 */
class BqsSheetFactory extends Factory
{
    protected $model = BqsSheet::class;

    /**
     * Define the model's default state.
     *
     * `buyer_id` and `bqs_date` are copied from the import rather than generated
     * independently: they are the same fact on both tables, and a factory that let
     * them disagree would make a buyer-scope test pass for the wrong reason.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bqs_import_id' => BqsImport::factory(),
            'buyer_id' => fn (array $attributes): int => BqsImport::withoutBuyerScope()
                ->findOrFail($attributes['bqs_import_id'])->buyer_id,
            'bqs_date' => fn (array $attributes): string => BqsImport::withoutBuyerScope()
                ->findOrFail($attributes['bqs_import_id'])->bqs_date->toDateString(),
            'root_id' => null,
            'fye' => (string) $this->faker->numberBetween(2026, 2030),
            'season' => $this->faker->randomElement(['SS', 'AW']),
            'department' => $this->faker->randomElement(['GIRLSWEAR', 'BOYSWEAR', 'MENSWEAR']),
            'title' => 'BQS '.$this->faker->bothify('??####').'.xlsx',
            'revision_no' => 1,
            'is_current' => true,
            'source_hash' => hash('sha256', $this->faker->unique()->uuid()),
            'row_count' => 1,
            'parse_status' => BqsParseStatus::Success,
            'payload' => ['warnings' => []],
        ];
    }

    /**
     * A revision that has been superseded by a later one.
     */
    public function superseded(): static
    {
        return $this->state(fn (): array => ['is_current' => false]);
    }

    /**
     * Revision 1 is its own root, which the import service writes after the insert.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BqsSheet $sheet): void {
            if ($sheet->root_id === null) {
                $sheet->forceFill(['root_id' => $sheet->id])->save();
            }
        });
    }
}
