<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves `/`, which renders nothing.
 *
 * There is no public landing page (ARCHITECTURE.md §6.2): authenticated users
 * go to the dashboard, guests to the login screen. The route keeps the `home`
 * name because the auth layouts and Wayfinder's generated `home()` helper both
 * depend on it resolving.
 *
 * This replaced an inline closure so the redirect is testable and consistent
 * with every other route. It is **not** a route-caching fix: Laravel 13
 * serializes closure routes via `SerializableClosure`, so the old closure
 * cached fine. It lives at the root of `app/Http/Controllers/` rather than
 * under a module because `/` belongs to no module.
 */
class HomeController extends Controller
{
    /**
     * Send the visitor wherever they actually belong.
     */
    public function __invoke(): RedirectResponse
    {
        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
