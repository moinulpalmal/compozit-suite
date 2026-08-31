<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RbacValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PermissionStoreRequest extends FormRequest
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
            'name' => $this->permissionNameRules(),
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
