<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
|
| Read-only, cross-module reporting. Reports never write domain data; they
| read through report services. Every route here is name-prefixed with
| `reports.` and URL-prefixed with `reports`.
|
| See ARCHITECTURE.md → "Module 5 — Reports".
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        //
    });
