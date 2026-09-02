<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\BqsFileType;
use App\Services\Admin\BuyerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a BQS workbook upload.
 *
 * **`buyer_id` is checked against the uploader's own buyer access**, not merely
 * against `buyers` — the same guarantee `PurchaseOrderImportRequest` documents.
 * Importing into a buyer the uploader cannot see would succeed and `BuyerScope` would
 * then hide the result on the redirect: a success message followed by an empty table,
 * which reads as a bug and is not one. See ARCHITECTURE.md §9.2.
 *
 * **`bqs_date` is required and comes from the form.** The workbook carries no date of
 * any kind — no document date, no revision date — so unlike every other field on a
 * BQS it cannot be read. It is master data the uploader supplies, which is why it sits
 * beside the buyer picker rather than being inferred from the file's timestamp: a file
 * copied between machines carries whatever date the copy happened on.
 *
 * The `mimes:` rule checks what the browser claimed. What the file *is* gets decided
 * from its magic bytes by {@see BqsFileType::detect()}.
 */
class BqsImportRequest extends FormRequest
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
                'required',
                'integer',
                Rule::in(array_keys(app(BuyerService::class)->assignableToActor())),
            ],
            'bqs_date' => ['required', 'date'],
            'file' => [
                'required',
                'file',
                'max:'.config('bqs-import.limits.max_file_size_kb'),
                'mimes:xlsx,xls',
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
            'buyer_id.required' => __('Choose which buyer this BQS belongs to.'),
            'buyer_id.in' => __('You do not have access to that buyer.'),
            'bqs_date.required' => __('Enter the BQS date.'),
            'bqs_date.date' => __('That is not a valid date.'),
            'file.required' => __('Choose a BQS workbook to import.'),
            'file.mimes' => __('Only .xlsx and .xls workbooks can be imported.'),
            'file.max' => __('That file is larger than the maximum allowed size.'),
        ];
    }
}
