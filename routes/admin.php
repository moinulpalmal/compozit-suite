<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BuyerController;
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
         * Buyer access is edited where the user is, in a dialog beside the roles
         * one — there is no buyer-access page. `admin.buyer-access.view` gates
         * whether the users list carries buyer data at all; this gates changing
         * it. See ARCHITECTURE.md §9.2.
         */
        Route::put('users/{user}/buyer-access', [UserController::class, 'updateBuyerAccess'])
            ->name('users.buyer-access')
            ->middleware('permission:admin.buyer-access.update');

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

        /*
         * Buyers — the unit every buyer-owned record is scoped by. One page with
         * modals, like designations: retired with `status`, never soft-deleted.
         *
         * The options endpoint is declared before the resource so the literal
         * segment is never shadowed by a model-bound one. It answers to whoever
         * administers buyers *or* assigns access to them — the access dialog on
         * `admin/users` needs the picker without needing `admin.buyers.view`.
         */
        Route::get('buyers/options', [BuyerController::class, 'options'])
            ->name('buyers.options')
            ->middleware('permission:admin.buyers.view|admin.buyer-access.update');

        Route::resource('buyers', BuyerController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['index'], 'permission:admin.buyers.view')
            ->middlewareFor(['store'], 'permission:admin.buyers.create')
            ->middlewareFor(['update'], 'permission:admin.buyers.update')
            ->middlewareFor(['destroy'], 'permission:admin.buyers.delete');

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

        /*
         * The audit trail — ARCHITECTURE.md §9.3. Read-only: there is no `store`,
         * `update` or `destroy`, and no permission for one, because a trail an
         * administrator can edit answers nothing.
         *
         * `admin.audit-logs.view` reaches only `super-admin`. That is enforced by
         * the seeder rather than by a `role:` middleware here, so §9.1's rule
         * against naming a role in a check still holds and widening access later
         * is a seeder line rather than a code change.
         *
         * `audit-logs/history` is declared before the index for the convention
         * `users/availability` and `buyers/options` already follow — a literal
         * segment must never be shadowed by a model-bound one.
         */
        Route::get('audit-logs/history', [AuditLogController::class, 'history'])
            ->name('audit-logs.history')
            ->middleware('permission:admin.audit-logs.view');

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index')
            ->middleware('permission:admin.audit-logs.view');
    });
