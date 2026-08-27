<?php

use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Module
|--------------------------------------------------------------------------
|
| User management, RBAC (roles & permissions), buyer setup, buyer-scoped
| access control, and audit logging. Every route here is name-prefixed
| with `admin.` and URL-prefixed with `admin`.
|
| See ARCHITECTURE.md → "Module 1 — Admin".
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        /*
         * User management. Create, update, role assignment, soft delete,
         * restore and permanent delete all happen on `admin/users`, which
         * switches between the active and historical lists with a `filter`
         * query parameter rather than a second route.
         */
        Route::get('users/availability', [UserController::class, 'availability'])
            ->name('users.availability')
            ->middleware('role_or_permission:admin.users.create|admin.users.update');

        Route::resource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['index'], 'permission:admin.users.view')
            ->middlewareFor(['store'], 'permission:admin.users.create')
            ->middlewareFor(['update'], 'permission:admin.users.update')
            ->middlewareFor(['destroy'], 'permission:admin.users.delete');

        Route::put('users/{user}/restore', [UserController::class, 'restore'])
            ->withTrashed()
            ->name('users.restore')
            ->middleware('permission:admin.users.restore');

        Route::delete('users/{user}/force', [UserController::class, 'forceDelete'])
            ->withTrashed()
            ->name('users.force-delete')
            ->middleware('permission:admin.users.force-delete');

        Route::put('users/{user}/password', [UserController::class, 'updatePassword'])
            ->name('users.password')
            ->middleware('permission:admin.users.reset-password');

        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])
            ->name('users.roles')
            ->middleware('permission:admin.users.assign-roles');

        /*
         * Designations — HR job titles. One page with modals, like users.
         * There is no `show`, and no restore/force-delete pair: a designation
         * is retired by setting its status, and deleting one anybody holds is
         * refused by the service.
         *
         * Activating and deactivating is part of `update` on purpose. Unlike
         * `admin.users.assign-roles`, toggling a descriptive label grants
         * nobody any power, so it needs no permission of its own.
         */
        /*
         * The async source for `<Combobox searchUrl>` — see ARCHITECTURE.md
         * §8.5. Declared before the resource so the literal segment is never
         * shadowed by a model-bound one, the same way `users/availability` is.
         */
        Route::get('designations/options', [DesignationController::class, 'options'])
            ->name('designations.options')
            ->middleware('permission:admin.designations.view');

        Route::resource('designations', DesignationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['index'], 'permission:admin.designations.view')
            ->middlewareFor(['store'], 'permission:admin.designations.create')
            ->middlewareFor(['update'], 'permission:admin.designations.update')
            ->middlewareFor(['destroy'], 'permission:admin.designations.delete');

        Route::resource('roles', RoleController::class)
            ->except('show')
            ->middlewareFor(['index'], 'permission:admin.roles.view')
            ->middlewareFor(['create', 'store'], 'permission:admin.roles.create')
            ->middlewareFor(['edit', 'update'], 'permission:admin.roles.update')
            ->middlewareFor(['destroy'], 'permission:admin.roles.delete');

        Route::resource('permissions', PermissionController::class)
            ->except('show')
            ->middlewareFor(['index'], 'permission:admin.permissions.view')
            ->middlewareFor(['create', 'store'], 'permission:admin.permissions.create')
            ->middlewareFor(['edit', 'update'], 'permission:admin.permissions.update')
            ->middlewareFor(['destroy'], 'permission:admin.permissions.delete');
    });
