<?php

namespace App\Http\Requests\Merchandising;

use App\Services\Merchandising\PoParser\TextExtractor\FileTypeDetector;
use App\Services\Merchandising\PurchaseOrderImportService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a purchase-order document upload.
 *
 * **`buyer_id` is checked against the uploader's own buyer access, not merely against
 * `buyers`.** That is the whole guarantee behind picking the buyer on the form: an
 * import into a buyer the uploader cannot see would succeed, and `BuyerScope` would
 * then hide the result the instant the redirect landed — a success message followed
 * by an empty table, which reads as a bug and is not one. Restricting the rule to the
 * same set the picker offers makes that state unreachable. See ARCHITECTURE.md §9.2.
 *
 * The `mimes:` rule checks what the browser claimed. What the file *is* gets decided
 * from its magic bytes by
 * {@see FileTypeDetector}.
 */
class PurchaseOrderImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('po-parser.accepted_extensions');

        return [
            'buyer_id' => [
                'required',
                'integer',
                Rule::in(array_keys(app(PurchaseOrderImportService::class)->assignableBuyers())),
            ],
            'file' => [
                'required',
                'file',
                'max:'.config('po-parser.limits.max_file_size_kb'),
                'mimes:'.implode(',', $extensions),
            ],
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
            'buyer_id.required' => __('Choose which buyer this purchase order belongs to.'),
            'buyer_id.in' => __('You do not have access to that buyer.'),
            'file.required' => __('Choose a purchase-order document to import.'),
            'file.mimes' => __('Only .doc, .docx, .rtf and .pdf files can be imported.'),
            'file.max' => __('That file is larger than the maximum allowed size.'),
        ];
    }
}
