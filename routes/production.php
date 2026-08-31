<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Production Module
|--------------------------------------------------------------------------
|
| Garments production features (cutting, sewing, finishing, packing and the
| line/output tracking around them). Every route here is name-prefixed with
| `production.` and URL-prefixed with `production`.
|
| See ARCHITECTURE.md → "Module 4 — Production".
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('production')
    ->name('production.')
    ->group(function (): void {
        //
    });
