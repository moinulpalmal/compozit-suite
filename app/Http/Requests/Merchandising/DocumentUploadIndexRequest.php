<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\DocumentType;
use App\Http\Requests\ListRequest;
use App\Models\Merchandising\DocumentUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the document library list's query string.
 *
 * Everything but the type rule comes from `ListRequest`; see ARCHITECTURE.md §8.6.
 *
 * **There is no `view` control here**, unlike the BQS and purchase-order lists. Those
 * choose between record *sets* — current revisions against every revision, usable
 * against failed — and a document library has only one set: nothing is revised and
 * nothing fails to parse, because nothing is parsed.
 */
class DocumentUploadIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return DocumentUpload::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return DocumentUpload::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * The type cell is a dropdown, so a value outside the enum is a malformed request
     * rather than a filter that finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'filter.file_type' => ['nullable', Rule::enum(DocumentType::class)],
        ];
    }
}
