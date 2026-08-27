<?php

namespace App\Concerns;

use App\Models\Admin\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validating a submitted list of role names.
 *
 * Extracted from {@see RbacValidationRules} so requests that assign roles —
 * the Admin user forms — can reuse it without also inheriting the name-format
 * machinery that only the role and permission forms need.
 */
trait RoleAssignmentRules
{
    /**
     * Get the validation rules used to validate a list of role names.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function roleListRules(): array
    {
        return [
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
        ];
    }

    /**
     * The submitted role names, validated by `roleListRules()`.
     *
     * @return list<string>
     */
    public function submittedRoles(): array
    {
        return array_values(array_filter($this->array('roles'), is_string(...)));
    }

    /**
     * Refuse to grant `super-admin` to anyone unless the actor holds it.
     *
     * Without this, `admin.users.assign-roles` is a privilege escalation: the
     * holder could grant themselves the role whose `Gate::before` bypass
     * passes every check in the application. The mirror case — *revoking*
     * super-admin — is guarded in `UserService`, which can see the target's
     * current roles.
     */
    protected function assignableRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== Role::SUPER_ADMIN) {
                return;
            }

            if (! $this->user()?->hasRole(Role::SUPER_ADMIN)) {
                $fail(__('Only a super admin may grant the super-admin role.'));
            }
        };
    }
}
