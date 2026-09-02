<?php

namespace Database\Factories\Merchandising;

use App\Enums\Merchandising\DocumentType;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\DocumentUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentUpload>
 */
class DocumentUploadFactory extends Factory
{
    protected $model = DocumentUpload::class;

    /**
     * Define the model's default state.
     *
     * **The default has a buyer**, so a factory-made batch behaves like the scoped
     * majority and a test that wants the unassigned case has to ask for it. Getting
     * that round the wrong way would make every buyer-scope assertion pass by
     * accident. {@see self::unassigned()} is the ask.
     *
     * `file_count` is 0 because no files are attached; a test that needs the two to
     * agree writes them together, as `DocumentLibraryService` does.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'file_type' => $this->faker->randomElement(DocumentType::cases()),
            'title' => $this->faker->sentence(3),
            'note' => null,
            'file_count' => 0,
        ];
    }

    /**
     * A batch that concerns no particular buyer, and is therefore visible to everyone.
     */
    public function unassigned(): static
    {
        return $this->state(fn (): array => ['buyer_id' => null]);
    }

    /**
     * A batch of a given type.
     */
    public function ofType(DocumentType $type): static
    {
        return $this->state(fn (): array => ['file_type' => $type]);
    }
}
