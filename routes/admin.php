<?php

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
        //
    });
