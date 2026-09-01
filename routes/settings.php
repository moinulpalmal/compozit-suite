<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\NotificationColorController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TnaTemplateController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
|
| Two halves that share a URL prefix and nothing else (ARCHITECTURE.md §5).
|
| The *account* routes below are deliberately unprefixed — `profile.edit`,
| `security.edit`, `appearance.edit`, `user-password.update` — because Fortify
| and the starter kit reference them by those names. Do not rename them.
|
| Every *new* Settings route uses the `settings.` name prefix, which is what the
| master-data group at the bottom of this file does.
|
*/

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
});

/*
|--------------------------------------------------------------------------
| Master data
|--------------------------------------------------------------------------
|
| Product and process reference tables (ARCHITECTURE.md §9.4). Every module
| reads these and only Settings writes them.
|
| One permission bucket serves the whole half: `settings.master-data.*` is
| already in the seeded catalogue and already granted, so a second master table
| adds a route group here and nothing else — no seeder entry, no role change.
| The trade is that whoever may edit colours may edit every reference table;
| that was chosen over per-table permissions, which would have silently broken
| the `merchandiser` role's literal `settings.master-data.view` grant.
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('settings/master-data')
    ->name('settings.master-data.')
    ->group(function (): void {
        Route::resource('notification-colors', NotificationColorController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['index'], 'permission:settings.master-data.view')
            ->middlewareFor(['store'], 'permission:settings.master-data.create')
            ->middlewareFor(['update'], 'permission:settings.master-data.update')
            ->middlewareFor(['destroy'], 'permission:settings.master-data.delete');

        /*
         * TNA schedules, read by the Merchandising TNA board. The second master table,
         * and the demonstration of the claim above: this group is the entire cost —
         * no permission, no seeder entry, no role change.
         */
        Route::resource('tna-templates', TnaTemplateController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['index'], 'permission:settings.master-data.view')
            ->middlewareFor(['store'], 'permission:settings.master-data.create')
            ->middlewareFor(['update'], 'permission:settings.master-data.update')
            ->middlewareFor(['destroy'], 'permission:settings.master-data.delete');
    });
