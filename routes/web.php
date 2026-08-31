<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/**
 * There is no public landing page. `/` keeps the `home` name because the auth
 * layouts and Wayfinder's generated route helpers depend on `home()` resolving.
 *
 * The redirect moved from an inline closure to a controller so it is testable
 * and consistent with the rest of the table. Closures cache fine on Laravel 13
 * (`SerializableClosure`) — this was never a `route:cache` problem.
 */
Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/merchandising.php';
require __DIR__.'/production.php';
require __DIR__.'/reports.php';
