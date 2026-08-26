<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
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
