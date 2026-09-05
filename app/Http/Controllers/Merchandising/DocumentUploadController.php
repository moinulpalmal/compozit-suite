<?php

namespace App\Http\Controllers\Merchandising;

use App\Enums\Merchandising\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\Merchandising\DocumentUploadIndexRequest;
use App\Http\Requests\Merchandising\DocumentUploadStoreRequest;
use App\Models\Merchandising\DocumentFile;
use App\Models\Merchandising\DocumentUpload;
use App\Models\Scopes\BuyerScope;
use App\Services\Admin\BuyerService;
use App\Services\Merchandising\BqsImportService;
use App\Services\Merchandising\DocumentLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The merchandising document library: batches of files, as they arrived.
 *
 * **This surface deliberately does not parse anything.** A batch typed
 * {@see DocumentType::Bqs} is a stored document and not an imported BQS — it writes no
 * `bqs_sheets` row and runs no reader. That is the decision, not an unfinished
 * half of one; wiring this to {@see BqsImportService} would
 * give the application two write paths to one fact, which ARCHITECTURE.md §5 records
 * as how they drift. See
 * `documentation/merchandising.md` for how a user is meant to tell the two apart.
 *
 * **There is no policy**, for the reason {@see BqsController} records: the model is
 * buyer-scoped, so a batch outside the actor's access 404s at route-model binding
 * before anything is authorized, and a policy returning `true` would only obscure
 * that. The nullable-buyer case is decided in the same place — {@see BuyerScope}
 * includes unassigned rows on purpose (§9.2).
 *
 * **There is no `create` route.** The upload form is a modal on the list page, like
 * every other create surface here.
 */
class DocumentUploadController extends Controller
{
    public function __construct(
        protected DocumentLibraryService $library,
        protected BuyerService $buyers,
    ) {}

    /**
     * List the uploaded batches.
     *
     * The list is over batches rather than files because §8.6 forbids grouped
     * rendering inside a paginated list — a batch cut across a page boundary — and a
     * batch is the unit somebody uploaded. The individual files are {@see self::show()}.
     *
     * The buyer options are gated on the create permission, so a read-only role does
     * not pay for the query, exactly as the two import dialogs are gated.
     */
    public function index(DocumentUploadIndexRequest $request): Response
    {
        $filters = $request->filters();

        $uploads = DocumentUpload::query()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->with(['buyer:id,name', 'insertedBy:id,name'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (DocumentUpload $upload): array => [
                'id' => $upload->id,
                'title' => $upload->title,
                'file_type' => $upload->file_type->value,
                'file_count' => $upload->file_count,
                'buyer' => $upload->buyer?->name,
                'uploaded_by' => $upload->insertedBy?->name,
                'created_at' => $upload->created_at?->toDateTimeString(),
            ]);

        $canCreate = (bool) $request->user()?->can('merchandising.documents.create');

        return Inertia::render('merchandising/documents/index', [
            'uploads' => $uploads,
            'uploadBuyers' => $canCreate ? $this->buyers->assignableOptions() : [],
            'documentTypes' => DocumentType::options(),
            'maxFilesPerBatch' => (int) config('merchandising-documents.limits.max_files_per_batch'),
            'allowedExtensions' => config('merchandising-documents.allowed_extensions'),
            'sortable' => DocumentUpload::SORTABLE,
            'filterable' => DocumentUpload::FILTERABLE,
            'perPageOptions' => ListRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Show one batch and the files in it.
     *
     * **Not paginated.** The batch is capped at `max_files_per_batch` by the upload
     * itself, so the page is bounded by construction — the same argument
     * {@see BqsController::show()} makes about a workbook's rows.
     */
    public function show(DocumentUpload $documentUpload): Response
    {
        $documentUpload->load(['buyer:id,name', 'insertedBy:id,name', 'documentFiles.insertedBy:id,name']);

        return Inertia::render('merchandising/documents/show', [
            'upload' => [
                'id' => $documentUpload->id,
                'title' => $documentUpload->title,
                'note' => $documentUpload->note,
                'file_type' => $documentUpload->file_type->value,
                'file_type_label' => $documentUpload->file_type->label(),
                'file_count' => $documentUpload->file_count,
                'buyer' => $documentUpload->buyer?->name,
                'uploaded_by' => $documentUpload->insertedBy?->name,
                'created_at' => $documentUpload->created_at?->toDateTimeString(),
            ],
            'files' => $documentUpload->documentFiles
                ->map(fn (DocumentFile $file): array => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'extension' => $file->extension,
                    'mime_type' => $file->mime_type,
                    'size_bytes' => $file->size_bytes,
                    'is_previewable' => $file->isPreviewable(),
                    'uploaded_by' => $file->insertedBy?->name,
                    'created_at' => $file->created_at?->toDateTimeString(),
                    'updated_at' => $file->updated_at?->toDateTimeString(),
                ])
                ->all(),
            'allowedExtensions' => config('merchandising-documents.allowed_extensions'),
        ]);
    }

    /**
     * Store a batch of uploaded files.
     */
    public function store(DocumentUploadStoreRequest $request): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = array_values($request->file('files', []));

        $upload = $this->library->store($files, $request->documentType(), [
            'buyer_id' => $request->integer('buyer_id') ?: null,
            'title' => $request->string('title')->value() ?: null,
            'note' => $request->string('note')->value() ?: null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Uploaded 1 file.|[2,*]Uploaded :count files.',
                $upload->file_count,
                ['count' => $upload->file_count],
            ),
        ]);

        return to_route('merchandising.documents.show', $upload);
    }

    /**
     * Delete a batch and every file in it.
     */
    public function destroy(DocumentUpload $documentUpload): RedirectResponse
    {
        $label = $documentUpload->title ?? __('the upload');
        $count = $documentUpload->file_count;

        $this->library->deleteUpload($documentUpload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Deleted :label and its 1 file.|[2,*]Deleted :label and its :count files.',
                $count,
                ['label' => $label, 'count' => $count],
            ),
        ]);

        return to_route('merchandising.documents.index');
    }
}
