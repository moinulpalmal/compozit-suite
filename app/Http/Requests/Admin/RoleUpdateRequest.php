<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RbacValidationRules;
use App\Models\Admin\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    use RbacValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => $this->roleNameRules($role instanceof Role ? $role->id : null),
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
