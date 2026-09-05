<?php

namespace App\Http\Requests\Merchandising;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a replacement for one file already in the library.
 *
 * **Replace needs `delete` as well as `update`, and that is the point of this class.**
 * There is no version history — the file it replaces is destroyed — so replacing is
 * deleting with an upload attached, and a user trusted to add documents is not thereby
 * trusted to destroy one. It is the same split {@see BqsResolveRequest} enforces for
 * `overwrite`, and the same reasoning that keeps `admin.users.assign-roles` apart from
 * `admin.users.update`.
 *
 * The UI hides the action from a user without both, so this check is the backstop
 * against a hand-made request rather than the thing anybody meets.
 *
 * The rules mirror {@see DocumentUploadStoreRequest} for one file: an extension
 * allow-list, and no size limit.
 */
class DocumentFileReplaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        return (bool) $actor?->can('merchandising.documents.update')
            && (bool) $actor->can('merchandising.documents.delete');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('merchandising-documents.allowed_extensions', []);

        return [
            'file' => ['required', 'file', 'extensions:'.implode(',', $extensions)],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Choose the file to put in its place.'),
            'file.extensions' => __('":input" is not a file type this library accepts.'),
        ];
    }
}
