<?php

namespace App\Concerns;

use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RbacValidationRules
{
    use RoleAssignmentRules;

    /**
     * Role names are kebab-case slugs: `production-manager`.
     */
    protected const string ROLE_NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Permission names are `{module}.{resource}.{action}`, each segment kebab-case:
     * `merchandising.tech-packs.update`. See ARCHITECTURE.md §9.1.
     */
    protected const string PERMISSION_NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*){2}$/';

    /**
     * Get the validation rules used to validate a role name.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function roleNameRules(?int $roleId = null): array
    {
        return [
            'required',
            'string',
            'max:125',
            'regex:'.self::ROLE_NAME_PATTERN,
            Rule::unique(Role::class, 'name')->ignore($roleId),
        ];
    }

    /**
     * Get the validation rules used to validate a permission name.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function permissionNameRules(?int $permissionId = null): array
    {
        return [
            'required',
            'string',
            'max:125',
            'regex:'.self::PERMISSION_NAME_PATTERN,
            Rule::unique(Permission::class, 'name')->ignore($permissionId),
        ];
    }

    /**
     * Get the validation rules used to validate a list of permission names.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function permissionListRules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /**
     * The submitted permission names, validated by `permissionListRules()`.
     *
     * @return list<string>
     */
    public function submittedPermissions(): array
    {
        return array_values(array_filter($this->array('permissions'), is_string(...)));
    }

    /**
     * Human-readable messages for the pattern rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => $this->nameFormatMessage(),
        ];
    }

    /**
     * The message shown when a name does not match its pattern.
     */
    abstract protected function nameFormatMessage(): string;
}
