<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * There is no public landing page. `/` keeps the `home` name because the auth
 * layouts and Wayfinder's generated route helpers depend on `home()` resolving.
 */
Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'auth.session', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/merchandising.php';
require __DIR__.'/production.php';
require __DIR__.'/reports.php';
