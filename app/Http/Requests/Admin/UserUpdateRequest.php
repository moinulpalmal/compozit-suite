<?php

namespace App\Http\Requests\Admin;

use App\Concerns\EmployeeValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    use EmployeeValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Roles are deliberately absent: assigning them is a separate action
     * behind its own `admin.users.assign-roles` permission, so that editing a
     * profile does not imply the ability to widen someone's access.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user') instanceof User ? $this->route('user')->id : null;

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            ...$this->employeeRules($userId),
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
}
