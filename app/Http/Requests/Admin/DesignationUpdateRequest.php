<?php

namespace App\Http\Requests\Admin;

use App\Concerns\DesignationValidationRules;
use App\Models\Admin\Designation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DesignationUpdateRequest extends FormRequest
{
    use DesignationValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $designation = $this->route('designation');

        return $this->designationRules($designation instanceof Designation ? $designation->id : null);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->designationMessages();
    }
}
