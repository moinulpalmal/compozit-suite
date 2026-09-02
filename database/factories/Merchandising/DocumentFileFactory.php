<?php

namespace Database\Factories\Merchandising;

use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFile>
 */
class DocumentFileFactory extends Factory
{
    protected $model = DocumentFile::class;

    /**
     * Define the model's default state.
     *
     * **A factory-made row points at no real object on the disk.** Anything testing a
     * download, a preview or a delete has to put the file there — `Storage::fake()`
     * plus `UploadedFile::fake()` through `DocumentLibraryService`, which is the only
     * writer of that path. This factory is for the rows a list or a scope assertion
     * needs, and the `stored_path` it invents follows the real shape so nothing reads
     * as valid that is not.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'xlsx', 'docx', 'jpg']);
        $root = trim((string) config('merchandising-documents.storage.root'), '/');

        return [
            'document_upload_id' => DocumentUpload::factory(),
            'original_name' => $this->faker->word().'.'.$extension,
            'stored_path' => $root.'/'.$this->faker->numberBetween(1, 999).'/'.$this->faker->uuid().'.'.$extension,
            'extension' => $extension,
            'mime_type' => 'application/octet-stream',
            'size_bytes' => $this->faker->numberBetween(1_024, 5_000_000),
            'file_hash' => hash('sha256', $this->faker->unique()->uuid()),
        ];
    }
}
