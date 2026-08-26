<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RbacValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    use RbacValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->roleNameRules(),
            ...$this->permissionListRules(),
        ];
    }

    /**
     * The message shown when a name does not match its pattern.
     */
    protected function nameFormatMessage(): string
    {
        return __('The role name must be lowercase kebab-case, such as "production-manager".');
    }
}
