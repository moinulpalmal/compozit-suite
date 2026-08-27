<?php

namespace App\Http\Requests\Admin;

use App\Concerns\RoleAssignmentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserRoleUpdateRequest extends FormRequest
{
    use RoleAssignmentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->roleListRules(),
            'roles.*' => [...$this->roleListRules()['roles.*'], $this->assignableRoleRule()],
        ];
    }
}
