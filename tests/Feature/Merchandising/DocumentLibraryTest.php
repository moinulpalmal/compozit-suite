<?php

use App\Enums\Merchandising\DocumentType;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| The merchandising document library
|--------------------------------------------------------------------------
|
| A collect-only surface: it stores files and reads nothing out of them. So
| there is nothing here about parsing, revisions or conflict resolution — the
| three things the BQS and purchase-order import tests are mostly made of — and
| the interesting cases are instead about *access* and *the disk*:
|
|   - a nullable `buyer_id` means "everyone", and `BuyerScoped` would have made
|     it mean "nobody" (ARCHITECTURE.md §9.2);
|   - a child table with no scope of its own is only safe because its routes are
|     `scopeBindings()`-bound under the parent;
|   - a row and its stored object must not be able to disagree.
|
*/

const DOC_VIEW = 'merchandising.documents.view';
const DOC_CREATE = 'merchandising.documents.create';
const DOC_UPDATE = 'merchandising.documents.update';
const DOC_DELETE = 'merchandising.documents.delete';

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'Walmart']);
});

/** A user who may work with documents, holding the given buyer. */
function documentUser(?Buyer $buyer = null, string ...$permissions): User
{
    $user = userWithPermissions(...($permissions ?: [DOC_VIEW, DOC_CREATE]));

    if ($buyer instanceof Buyer) {
        $user->buyers()->attach($buyer);
    }

    return $user;
}

/**
 * A batch of fake uploads, as the form sends them.
 *
 * @return list<UploadedFile>
 */
function documentFiles(int $count = 2): array
{
    return collect(range(1, $count))
        ->map(fn (int $i): UploadedFile => UploadedFile::fake()->create("sheet-{$i}.xlsx", 16))
        ->all();
}

it('stores a batch of files and counts them', function (): void {
    $user = documentUser($this->buyer);

    $response = $this->actingAs($user)->post(route('merchandising.documents.store'), [
        'file_type' => DocumentType::SizeChart->value,
        'buyer_id' => $this->buyer->id,
        'title' => 'Spring size charts',
        'files' => documentFiles(3),
    ]);

    $upload = DocumentUpload::query()->sole();

    $response->assertRedirect(route('merchandising.documents.show', $upload));
    assertToast($response, 'success');

    expect($upload->file_type)->toBe(DocumentType::SizeChart)
        ->and($upload->file_count)->toBe(3)
        ->and($upload->inserted_by)->toBe($user->id)
        ->and($upload->documentFiles)->toHaveCount(3);

    foreach ($upload->documentFiles as $file) {
        Storage::disk('local')->assertExists($file->stored_path);
    }
});

it('never puts the uploader\'s filename on the disk', function (): void {
    $user = documentUser($this->buyer);

    $this->actingAs($user)->post(route('merchandising.documents.store'), [
        'file_type' => DocumentType::Other->value,
        'files' => [UploadedFile::fake()->create('../../evil name.pdf', 4)],
    ]);

    $file = DocumentFile::query()->sole();

    /*
     * The name is kept for the download header and nowhere else. If it ever
     * reaches the path, a crafted one escapes the batch directory.
     */
    expect($file->original_name)->not->toContain('..')
        ->and($file->stored_path)->not->toContain('evil')
        ->and($file->stored_path)->toStartWith('merchandising-documents/');
});

it('refuses more files than PHP will actually deliver', function (): void {
    $max = (int) config('merchandising-documents.limits.max_files_per_batch');

    $this->actingAs(documentUser($this->buyer))
        ->post(route('merchandising.documents.store'), [
            'file_type' => DocumentType::Other->value,
            'files' => documentFiles($max + 1),
        ])
        ->assertSessionHasErrors('files');

    expect(DocumentUpload::query()->count())->toBe(0);
});

it('refuses an extension outside the allow-list', function (): void {
    $this->actingAs(documentUser($this->buyer))
        ->post(route('merchandising.documents.store'), [
            'file_type' => DocumentType::Other->value,
            /* Absent from the list on purpose: it renders inline and can carry script. */
            'files' => [UploadedFile::fake()->create('payload.svg', 2)],
        ])
        ->assertSessionHasErrors('files.0');
});

it('refuses a buyer the uploader cannot see', function (): void {
    $other = Buyer::factory()->create(['name' => 'George']);

    $this->actingAs(documentUser($this->buyer))
        ->post(route('merchandising.documents.store'), [
            'file_type' => DocumentType::Bqs->value,
            'buyer_id' => $other->id,
            'files' => documentFiles(1),
        ])
        ->assertSessionHasErrors('buyer_id');
});

/*
|--------------------------------------------------------------------------
| Buyer scope — the reason this surface needed a new trait
|--------------------------------------------------------------------------
*/

it('scopes the list to accessible and unassigned batches', function (): void {
    DocumentUpload::factory()->unassigned()->create(['title' => 'Everyone size chart']);
    DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id, 'title' => 'Walmart pack']);
    DocumentUpload::factory()->create([
        'buyer_id' => Buyer::factory()->create()->id,
        'title' => 'Hidden pack',
    ]);

    $this->actingAs(documentUser($this->buyer, DOC_VIEW))
        ->get(route('merchandising.documents.index'))
        ->assertInertia(fn ($page) => $page->has('uploads.data', 2))
        ->assertSee('Everyone size chart')
        ->assertSee('Walmart pack')
        ->assertDontSee('Hidden pack');
});

it('does not leak another buyer\'s rows through the unassigned OR', function (): void {
    /*
     * The regression this pins: an ungrouped `orWhereNull` binds looser than the
     * filter, so a filtered list would read as
     * `(title LIKE … AND buyer_id IN (…)) OR buyer_id IS NULL` and return every
     * unassigned row regardless of the filter — and, with the filter on the other
     * side of the AND, rows the filter excluded.
     */
    DocumentUpload::factory()->unassigned()->create(['title' => 'Zebra chart']);
    DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id, 'title' => 'Alpha pack']);

    $this->actingAs(documentUser($this->buyer, DOC_VIEW))
        ->get(route('merchandising.documents.index', ['filter' => ['title' => 'Alpha']]))
        ->assertInertia(fn ($page) => $page
            ->has('uploads.data', 1)
            ->where('uploads.data.0.title', 'Alpha pack'));
});

it('404s a batch belonging to a buyer the actor cannot see', function (): void {
    $hidden = DocumentUpload::factory()->create([
        'buyer_id' => Buyer::factory()->create()->id,
    ]);

    $this->actingAs(documentUser($this->buyer, DOC_VIEW))
        ->get(route('merchandising.documents.show', $hidden))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Files: scoped binding, download, preview, replace, delete
|--------------------------------------------------------------------------
*/

/** Store one real batch and hand back its single file. */
function storedFile(Buyer $buyer, User $user, string $name = 'chart.pdf'): DocumentFile
{
    test()->actingAs($user)->post(route('merchandising.documents.store'), [
        'file_type' => DocumentType::SizeChart->value,
        'buyer_id' => $buyer->id,
        'files' => [UploadedFile::fake()->create($name, 8)],
    ]);

    return DocumentFile::query()->sole();
}

it('404s a file requested under the wrong batch', function (): void {
    $user = documentUser($this->buyer);
    $file = storedFile($this->buyer, $user);

    $decoy = DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id]);

    /*
     * `DocumentFile` has no buyer scope of its own — it reaches its buyer through
     * its parent — so `scopeBindings()` on the route is the entire guard. Without
     * it this resolves and serves the file.
     */
    $this->actingAs($user)
        ->get(route('merchandising.documents.files.download', [
            'documentUpload' => $decoy->id,
            'documentFile' => $file->id,
        ]))
        ->assertNotFound();
});

it('downloads a file under the name it arrived with', function (): void {
    $user = documentUser($this->buyer);
    $file = storedFile($this->buyer, $user, 'Size chart FW26.pdf');

    $this->actingAs($user)
        ->get(route('merchandising.documents.files.download', [
            'documentUpload' => $file->document_upload_id,
            'documentFile' => $file->id,
        ]))
        ->assertOk()
        ->assertDownload('Size chart FW26.pdf');
});

it('sends nosniff when it renders a file inline', function (): void {
    $user = documentUser($this->buyer);
    $file = storedFile($this->buyer, $user, 'chart.pdf');

    $response = $this->actingAs($user)
        ->get(route('merchandising.documents.files.preview', [
            'documentUpload' => $file->document_upload_id,
            'documentFile' => $file->id,
        ]));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

it('falls back to a download for a file no browser can render', function (): void {
    $user = documentUser($this->buyer);
    $file = storedFile($this->buyer, $user, 'plan.xlsx');

    $response = $this->actingAs($user)
        ->get(route('merchandising.documents.files.preview', [
            'documentUpload' => $file->document_upload_id,
            'documentFile' => $file->id,
        ]));

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('replaces a file and destroys the one it replaced', function (): void {
    $user = documentUser($this->buyer, DOC_VIEW, DOC_CREATE, DOC_UPDATE, DOC_DELETE);
    $file = storedFile($this->buyer, $user, 'old.pdf');

    $previous = $file->stored_path;

    $response = $this->actingAs($user)
        ->post(route('merchandising.documents.files.update', [
            'documentUpload' => $file->document_upload_id,
            'documentFile' => $file->id,
        ]), ['file' => UploadedFile::fake()->create('new.pdf', 12)]);

    assertToast($response, 'success');

    $file->refresh();

    expect($file->original_name)->toBe('new.pdf')
        ->and($file->stored_path)->not->toBe($previous)
        ->and($file->last_updated_by)->toBe($user->id);

    Storage::disk('local')->assertMissing($previous);
    Storage::disk('local')->assertExists($file->stored_path);
});

it('refuses a replacement from someone who may update but not delete', function (): void {
    $owner = documentUser($this->buyer, DOC_VIEW, DOC_CREATE, DOC_UPDATE, DOC_DELETE);
    $file = storedFile($this->buyer, $owner);

    /* Replace destroys the old file, so `update` alone is not enough. */
    $weak = documentUser($this->buyer, DOC_VIEW, DOC_UPDATE);

    $this->actingAs($weak)
        ->post(route('merchandising.documents.files.update', [
            'documentUpload' => $file->document_upload_id,
            'documentFile' => $file->id,
        ]), ['file' => UploadedFile::fake()->create('new.pdf', 4)])
        ->assertForbidden();

    Storage::disk('local')->assertExists($file->stored_path);
});

it('deletes one file and keeps the batch', function (): void {
    $user = documentUser($this->buyer, DOC_VIEW, DOC_CREATE, DOC_DELETE);

    $this->actingAs($user)->post(route('merchandising.documents.store'), [
        'file_type' => DocumentType::Other->value,
        'buyer_id' => $this->buyer->id,
        'files' => documentFiles(2),
    ]);

    $upload = DocumentUpload::query()->sole();
    $file = $upload->documentFiles->first();
    $path = $file->stored_path;

    $this->actingAs($user)->delete(route('merchandising.documents.files.destroy', [
        'documentUpload' => $upload->id,
        'documentFile' => $file->id,
    ]))->assertRedirect(route('merchandising.documents.show', $upload));

    Storage::disk('local')->assertMissing($path);

    expect($upload->refresh()->file_count)->toBe(1)
        ->and($upload->documentFiles()->count())->toBe(1);
});

it('deletes a batch and everything on the disk with it', function (): void {
    $user = documentUser($this->buyer, DOC_VIEW, DOC_CREATE, DOC_DELETE);

    $this->actingAs($user)->post(route('merchandising.documents.store'), [
        'file_type' => DocumentType::Other->value,
        'buyer_id' => $this->buyer->id,
        'files' => documentFiles(3),
    ]);

    $upload = DocumentUpload::query()->sole();
    $directory = $upload->storageDirectory();

    $this->actingAs($user)
        ->delete(route('merchandising.documents.destroy', $upload))
        ->assertRedirect(route('merchandising.documents.index'));

    expect(DocumentUpload::query()->count())->toBe(0)
        ->and(DocumentFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($directory))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Permission gating
|--------------------------------------------------------------------------
|
| Every route, because hiding a link is not authorization (§9.1) and the
| sidebar is the only other thing standing between a user and these URLs.
|
*/

it('refuses the list to a user without view', function (): void {
    $this->actingAs(documentUser($this->buyer, DOC_CREATE))
        ->get(route('merchandising.documents.index'))
        ->assertForbidden();
});

it('refuses an upload to a user without create', function (): void {
    $this->actingAs(documentUser($this->buyer, DOC_VIEW))
        ->post(route('merchandising.documents.store'), [
            'file_type' => DocumentType::Other->value,
            'files' => documentFiles(1),
        ])
        ->assertForbidden();
});

it('refuses deleting a batch to a user without delete', function (): void {
    $upload = DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id]);

    $this->actingAs(documentUser($this->buyer, DOC_VIEW, DOC_CREATE, DOC_UPDATE))
        ->delete(route('merchandising.documents.destroy', $upload))
        ->assertForbidden();

    expect(DocumentUpload::query()->count())->toBe(1);
});

it('refuses a download to a user without view', function (): void {
    $upload = DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id]);
    $file = DocumentFile::factory()->create(['document_upload_id' => $upload->id]);

    $this->actingAs(documentUser($this->buyer, DOC_CREATE))
        ->get(route('merchandising.documents.files.download', [
            'documentUpload' => $upload->id,
            'documentFile' => $file->id,
        ]))
        ->assertForbidden();
});

it('404s a row whose file is no longer on the disk', function (): void {
    $upload = DocumentUpload::factory()->create(['buyer_id' => $this->buyer->id]);
    $file = DocumentFile::factory()->create(['document_upload_id' => $upload->id]);

    /* The factory writes no object — a restored database without its disk. */
    $this->actingAs(documentUser($this->buyer, DOC_VIEW))
        ->get(route('merchandising.documents.files.download', [
            'documentUpload' => $upload->id,
            'documentFile' => $file->id,
        ]))
        ->assertNotFound();
});
