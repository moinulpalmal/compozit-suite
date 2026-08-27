<?php

namespace App\Policies\Admin;

use App\Models\User;

/**
 * Record-level authorization for user administration.
 *
 * Route-level gating is done by the `permission:` middleware in
 * `routes/admin.php`; this policy backs `Gate::authorize()` calls and the `can`
 * props the Admin pages render against.
 *
 * The self-service and last-super-admin guards are deliberately *not* here —
 * `Gate::before` grants a super admin every ability, so a policy denial would
 * be bypassed. They live in `Admin\UserService` instead, alongside the reason
 * strings the controller flashes.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.users.view');
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('admin.users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.users.create');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('admin.users.update');
    }

    /**
     * Determine whether the user can soft-delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('admin.users.delete');
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('admin.users.restore');
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('admin.users.force-delete');
    }

    /**
     * Determine whether the user can set another user's password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $user->can('admin.users.reset-password');
    }

    /**
     * Determine whether the user can change another user's roles.
     */
    public function assignRoles(User $user, User $model): bool
    {
        return $user->can('admin.users.assign-roles');
    }
}
