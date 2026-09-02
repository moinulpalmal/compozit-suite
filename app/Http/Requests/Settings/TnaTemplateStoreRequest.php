<?php

namespace App\Http\Requests\Settings;

use App\Concerns\TnaTemplateValidationRules;
use App\Services\Settings\TnaTemplateService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TnaTemplateStoreRequest extends FormRequest
{
    use TnaTemplateValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->tnaTemplateRules();
    }

    /**
     * Apply the rules that span more than one field.
     *
     * @return array<int, callable>
     */
    public function after(TnaTemplateService $templates): array
    {
        return [
            fn (Validator $validator) => $this->validateTnaTemplate($validator, $templates),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->tnaTemplateMessages();
    }
}
