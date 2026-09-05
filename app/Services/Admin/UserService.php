<?php

namespace App\Services\Admin;

use App\Enums\Admin\AuditEvent;
use App\Enums\RecordStatus;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Creates, updates, deletes and restores users, and assigns their roles.
 *
 * The escalation guards live here rather than in `Admin\UserPolicy` on purpose.
 * `AppServiceProvider::configureAuthorization()` registers a `Gate::before`
 * that grants a super admin every ability, so a policy denial would simply be
 * bypassed for exactly the account that most needs the guard. The same
 * reasoning already governs the super-admin role in `Admin\RoleController`.
 */
class UserService
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Create a user and grant it the given roles.
     *
     * Admin-created accounts are trusted, so their email is marked verified
     * immediately — there is no self-registration flow to confirm it.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roles
     */
    public function create(array $attributes, string $password, array $roles = []): User
    {
        $user = new User($attributes);
        $user->password = $password;
        $user->email_verified_at = now();
        $user->save();

        $this->syncRoles($user, $roles);

        return $user;
    }

    /**
     * Update a user's profile and HR fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }

    /**
     * Replace a user's role set.
     *
     * @param  list<string>  $roles
     */
    public function assignRoles(User $user, array $roles): void
    {
        $this->syncRoles($user, $roles);
    }

    /**
     * Replace a user's roles and record the change.
     *
     * **Every role write in this class goes through here**, which is the point:
     * `model_has_roles` is a pivot that spatie writes as raw rows, firing no model
     * event, so a grant is invisible to the audit trail unless it is recorded
     * deliberately. `admin.users.assign-roles` is the permission
     * `documentation/admin.md` §2.5 calls a privilege escalation if unguarded —
     * a trail that cannot answer "who made them an admin" is not doing its job.
     *
     * `config('permission.events_enabled')` is left `false` on purpose. Turning it
     * on would supply `RoleAttachedEvent`/`RoleDetachedEvent`, but it also changes
     * `syncRoles()`'s own code path — it switches from one `detachRoles()` to a
     * `removeRole()` per current role — and it emits a detach-all/attach-all pair
     * even when nothing moved. Diffing here instead yields one row with a real
     * before and after, and no row at all when the set is unchanged.
     *
     * @param  list<string>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $before = $this->roleNames($user);

        $user->syncRoles($roles);

        $this->audits->recordChange(
            $user,
            AuditEvent::RolesChanged,
            ['roles' => $before],
            ['roles' => $this->roleNames($user)],
        );
    }

    /**
     * A user's roles by name, in a stable order.
     *
     * Sorted because `recordChange()` compares arrays: unsorted, the database
     * returning the same roles in a different order would read as a change.
     *
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        $names = $user->roles()->pluck('name')->all();

        sort($names);

        return $names;
    }

    /**
     * Set a user's password.
     */
    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
    }

    /**
     * Soft-delete a user, moving it to the historical list.
     */
    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(User $user): void
    {
        $user->restore();
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete(User $user): void
    {
        $user->forceDelete();
    }

    /**
     * The reason the actor may not change this user's roles, if there is one.
     *
     * Nobody edits their own roles — otherwise an `admin.users.assign-roles`
     * holder can quietly widen their own access with no second pair of eyes.
     * Revoking `super-admin` is restricted to super admins, mirroring the grant
     * guard in `RoleAssignmentRules::assignableRoleRule()`.
     *
     * @param  list<string>  $roles
     */
    public function roleAssignmentBlocker(User $user, array $roles): ?string
    {
        if ($this->isActor($user)) {
            return __('You cannot change your own roles. Ask another administrator.');
        }

        $losesSuperAdmin = $user->hasRole(Role::SUPER_ADMIN) && ! in_array(Role::SUPER_ADMIN, $roles, true);

        if ($losesSuperAdmin && ! $this->actorIsSuperAdmin()) {
            return __('Only a super admin may revoke the super-admin role.');
        }

        return null;
    }

    /**
     * The reason the actor may not delete this user, if there is one.
     *
     * Soft deletes and force deletes are blocked for the same two reasons.
     */
    public function deletionBlocker(User $user): ?string
    {
        if ($this->isActor($user)) {
            return __('You cannot delete your own account here. Use your profile settings instead.');
        }

        if ($this->isLastSuperAdmin($user)) {
            return __('This is the last super admin. Grant the role to someone else first.');
        }

        return null;
    }

    /**
     * The reason the actor may not deactivate this user, if there is one.
     */
    public function statusBlocker(User $user, RecordStatus $status): ?string
    {
        if ($status === RecordStatus::Active) {
            return null;
        }

        if ($this->isActor($user)) {
            return __('You cannot deactivate your own account.');
        }

        if ($this->isLastSuperAdmin($user)) {
            return __('This is the last super admin and cannot be deactivated.');
        }

        return null;
    }

    /**
     * Determine whether this user is the only remaining super admin.
     *
     * Losing the last one leaves nobody able to administer the application,
     * which no other guard would catch.
     */
    public function isLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole(Role::SUPER_ADMIN)) {
            return false;
        }

        return User::query()
            ->role(Role::SUPER_ADMIN)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }

    /**
     * Determine whether the given user is the one making the request.
     */
    public function isActor(User $user): bool
    {
        return Auth::id() === $user->getKey();
    }

    /**
     * Determine whether the actor holds the super-admin role.
     */
    public function actorIsSuperAdmin(): bool
    {
        $actor = Auth::user();

        return $actor instanceof User && $actor->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Every role name the actor is allowed to put in front of the picker.
     *
     * `super-admin` is filtered out for everyone else so it can neither be
     * granted nor accidentally revoked from the UI.
     *
     * @return list<string>
     */
    public function assignableRoleNames(): array
    {
        $query = Role::query()->orderBy('name');

        if (! $this->actorIsSuperAdmin()) {
            $query->whereNot('name', Role::SUPER_ADMIN);
        }

        return array_values(array_filter($query->pluck('name')->all(), is_string(...)));
    }
}
