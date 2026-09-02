<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\DocumentType;
use App\Services\Admin\BuyerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a batch of files uploaded to the document library.
 *
 * Three of its rules are decisions rather than defaults, and each has cost somebody
 * time somewhere:
 *
 * **`buyer_id` is optional but access-checked.** Optional, because a size chart or a
 * TNA formula often concerns no single buyer and a null means "everyone sees it"
 * (ARCHITECTURE.md §9.2). Access-checked when it *is* given, for the reason
 * {@see BqsImportRequest} documents: uploading into a buyer you cannot see succeeds
 * and then `BuyerScope` hides the result, which is a success message followed by an
 * empty table.
 *
 * **The file cap is PHP's `max_file_uploads`, not a policy.** Files past that count
 * never reach PHP — they are dropped from `$_FILES` with no warning and no error — so
 * without this rule a user selecting twenty-five files would be told twenty were
 * stored and never learn about the other five. The message says "send the rest as a
 * second batch" because that is the actual remedy; raising the limit means editing
 * `php.ini`, not this file. There is deliberately **no per-file size rule**:
 * `upload_max_filesize` and `post_max_size` are the ceiling, by decision.
 *
 * **`extensions:`, not `mimes:`.** `mimes:` guesses the type from content and guesses
 * legacy Office containers wrong — `mimes:doc` rejects real `.doc` files. The browser's
 * claimed MIME type is stored for display and never trusted for validation.
 */
class DocumentUploadStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buyer_id' => [
                'nullable',
                'integer',
                Rule::in(array_keys(app(BuyerService::class)->assignableToActor())),
            ],
            'file_type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'files' => ['required', 'array', 'max:'.$this->maxFiles()],
            'files.*' => [
                'required',
                'file',
                'extensions:'.implode(',', $this->allowedExtensions()),
            ],
        ];
    }

    /**
     * The type of document this batch holds.
     */
    public function documentType(): DocumentType
    {
        return DocumentType::from((string) $this->validated()['file_type']);
    }

    /**
     * How many files one submission may carry.
     */
    public function maxFiles(): int
    {
        return (int) config('merchandising-documents.limits.max_files_per_batch');
    }

    /**
     * The extensions the library accepts.
     *
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('merchandising-documents.allowed_extensions', []);

        return $extensions;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buyer_id.in' => __('You do not have access to that buyer.'),
            'file_type.required' => __('Choose what kind of documents these are.'),
            'file_type.enum' => __('That is not a document type this library holds.'),
            'files.required' => __('Choose at least one file to upload.'),
            /*
             * Names the cause, because the cause is not obvious and the remedy is not
             * "try again" — the browser will happily attach the extra files and PHP
             * will discard them.
             */
            'files.max' => __('Up to :count files per upload — please send the rest as a second batch.', [
                'count' => $this->maxFiles(),
            ]),
            'files.*.file' => __('One of the selected items is not a file.'),
            'files.*.extensions' => __('":input" is not a file type this library accepts.'),
        ];
    }
}
