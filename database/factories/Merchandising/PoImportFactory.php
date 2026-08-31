<?php

namespace Database\Factories\Merchandising;

use App\Enums\Merchandising\PoFileType;
use App\Enums\Merchandising\PoParseStatus;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoImport>
 */
class PoImportFactory extends Factory
{
    protected $model = PoImport::class;

    /**
     * Define the model's default state.
     *
     * The payload is left empty rather than faked: a realistic one is the output of
     * parsing a real document, and `PoParserTest` gets that from the fixtures. A
     * factory that invented a plausible-looking payload would let a test pass against
     * a shape the parser never produces.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'source_file_name' => 'PO-'.$this->faker->bothify('??######').'.docx',
            'stored_path' => null,
            'detected_file_type' => PoFileType::Docx,
            'template_fingerprint' => substr(sha1($this->faker->unique()->word()), 0, 12),
            'page_count' => $this->faker->numberBetween(1, 30),
            'po_count' => 1,
            'parse_status' => PoParseStatus::Success,
            'confidence' => 1.000,
            'payload' => [],
        ];
    }

    /**
     * An import whose document could not be read cleanly.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'parse_status' => PoParseStatus::Failed,
            'confidence' => 0.800,
        ]);
    }
}
