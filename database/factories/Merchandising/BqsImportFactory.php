<?php

namespace Database\Factories\Merchandising;

use App\Enums\Merchandising\BqsFileType;
use App\Enums\Merchandising\BqsParseStatus;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\BqsImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BqsImport>
 */
class BqsImportFactory extends Factory
{
    protected $model = BqsImport::class;

    /**
     * Define the model's default state.
     *
     * The payload is left empty rather than faked, for the reason `PoImportFactory`
     * gives: a realistic one is the output of reading a real workbook, and
     * `BqsImportTest` gets that from the fixture. A factory inventing a plausible
     * header map would let a test pass against a shape the reader never produces.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'bqs_date' => $this->faker->dateTimeBetween('-1 year')->format('Y-m-d'),
            'source_file_name' => 'BQS '.$this->faker->bothify('??####').'.xlsx',
            'stored_path' => null,
            'detected_file_type' => BqsFileType::Xlsx,
            'sheet_name' => 'BQS Report',
            'header_fingerprint' => substr(sha1($this->faker->unique()->word()), 0, 12),
            'row_count' => 1,
            'parse_status' => BqsParseStatus::Success,
            'source_hash' => hash('sha256', $this->faker->unique()->uuid()),
            'payload' => [],
        ];
    }
}
