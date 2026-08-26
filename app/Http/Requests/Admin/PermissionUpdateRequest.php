<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RbacValidationRules;
use App\Models\Admin\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PermissionUpdateRequest extends FormRequest
{
    use RbacValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $permission = $this->route('permission');

        return [
            'name' => $this->permissionNameRules($permission instanceof Permission ? $permission->id : null),
            ...$this->roleListRules(),
        ];
    }

    /**
     * The message shown when a name does not match its pattern.
     */
    protected function nameFormatMessage(): string
    {
        return __('The permission name must read module.resource.action, such as "merchandising.tech-packs.update".');
    }
}
