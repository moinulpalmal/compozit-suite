<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchandising Module
|--------------------------------------------------------------------------
|
| Development tech packs, BQS & purchase orders, and fabric/accessory
| booking management. Every route here is name-prefixed with
| `merchandising.` and URL-prefixed with `merchandising`.
|
| See ARCHITECTURE.md → "Module 3 — Merchandising".
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('merchandising')
    ->name('merchandising.')
    ->group(function (): void {
        //
    });
