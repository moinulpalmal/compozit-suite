<?php

namespace App\Http\Requests\Admin;

use App\Concerns\EmployeeValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\RoleAssignmentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    use EmployeeValidationRules, PasswordValidationRules, ProfileValidationRules, RoleAssignmentRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            ...$this->employeeRules(),
            ...$this->roleListRules(),
            'roles.*' => [...$this->roleListRules()['roles.*'], $this->assignableRoleRule()],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }

    /**
     * The attributes that create a user, less the password and roles.
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return $this->safe()->except(['password', 'password_confirmation', 'roles']);
    }
}
