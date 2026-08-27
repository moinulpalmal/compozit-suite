<?php

namespace App\Concerns;

use App\Enums\Admin\DesignationStatus;
use App\Enums\Admin\Gender;
use App\Models\Admin\Designation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait EmployeeValidationRules
{
    /**
     * Employee IDs are 3–10 alphanumeric characters, optionally hyphenated:
     * `15868`, `EMP-01`.
     */
    public const string EMPLOYEE_ID_PATTERN = '/^[A-Za-z0-9-]{3,10}$/';

    /**
     * Bangladeshi mobile numbers: eleven digits beginning `013`–`019`.
     */
    public const string MOBILE_PATTERN = '/^01[3-9][0-9]{8}$/';

    /**
     * Internal extensions are up to four digits.
     */
    public const string EXTENSION_PATTERN = '/^[0-9]{1,4}$/';

    /**
     * Get the validation rules used to validate an employee ID.
     *
     * The uniqueness rule queries the table directly, so it sees soft-deleted
     * rows too — reusing the ID of a deleted user is refused rather than
     * silently colliding with the database's unique index.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function employeeIdRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'regex:'.self::EMPLOYEE_ID_PATTERN,
            Rule::unique(User::class, 'employee_id')->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate a mobile number.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function mobileRules(): array
    {
        return ['nullable', 'string', 'regex:'.self::MOBILE_PATTERN];
    }

    /**
     * Get the validation rules used to validate an internal extension.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function extensionRules(): array
    {
        return ['nullable', 'string', 'regex:'.self::EXTENSION_PATTERN];
    }

    /**
     * Get the validation rules used to validate a gender.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function genderRules(): array
    {
        return ['required', Rule::enum(Gender::class)];
    }

    /**
     * Get the validation rules used to validate a designation.
     *
     * `users.designation_id` is nullable in the database because rows created
     * before designations existed have none. It is required *here* so every
     * user created or edited from now on is given one, and so the omission is
     * reported as a field error rather than a driver constraint.
     *
     * Inactive designations are rejected, except the one the user already
     * holds — `DesignationService::assignableOptions()` is what puts that one
     * back in the picker, and this rule has to agree with it.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function designationRules(?int $userId = null): array
    {
        return [
            'required',
            'integer',
            Rule::exists(Designation::class, 'id')->where(
                function (Builder $query) use ($userId): void {
                    $query->where('status', DesignationStatus::Active->value);

                    if ($userId !== null) {
                        $query->orWhereIn('id', User::query()
                            ->whereKey($userId)
                            ->select('designation_id'));
                    }
                },
            ),
        ];
    }

    /**
     * Get the validation rules that apply to the HR fields shared by the
     * create and update forms.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function employeeRules(?int $userId = null): array
    {
        return [
            'employee_id' => $this->employeeIdRules($userId),
            'personal_mobile_no' => $this->mobileRules(),
            'official_mobile_no' => $this->mobileRules(),
            'official_extension_no' => $this->extensionRules(),
            'gender' => $this->genderRules(),
            'designation_id' => $this->designationRules($userId),
            'approved' => ['required', 'boolean'],
            'approval_authority' => ['required', 'boolean'],
        ];
    }

    /**
     * Messages that explain the patterns above, and the soft-delete collision.
     *
     * @return array<string, string>
     */
    protected function employeeMessages(): array
    {
        return [
            'employee_id.regex' => __('The employee ID must be 3–10 letters, digits or hyphens.'),
            'employee_id.unique' => __('That employee ID is already in use. If the user was deleted, restore them from the Historical tab.'),
            'email.unique' => __('That email address is already in use. If the user was deleted, restore them from the Historical tab.'),
            'personal_mobile_no.regex' => __('The personal mobile number must be 11 digits starting with 013–019.'),
            'official_mobile_no.regex' => __('The official mobile number must be 11 digits starting with 013–019.'),
            'official_extension_no.regex' => __('The extension must be up to 4 digits.'),
            'designation_id.required' => __('Please choose a designation.'),
            'designation_id.exists' => __('That designation is not available. Deactivated designations cannot be assigned.'),
        ];
    }
}
