<?php

namespace App\Http\Requests\Admin;

use App\Concerns\DepartmentValidationRules;
use App\Models\Admin\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentUpdateRequest extends FormRequest
{
    use DepartmentValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The department itself is already safe: `Department` is `BuyerScoped`, so
     * one outside the actor's access 404s at route-model binding before this
     * request runs. The `buyer_id` rule still matters, because *moving* a
     * department to a buyer the actor cannot see is a write this request would
     * otherwise accept.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');

        return $this->departmentRules($department instanceof Department ? $department->id : null);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->departmentMessages();
    }
}
