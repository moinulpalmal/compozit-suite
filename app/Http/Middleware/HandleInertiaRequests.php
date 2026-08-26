<?php

namespace App\Http\Middleware;

use App\Enums\Theme;
use App\Models\Admin\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $this->permissionsFor($request->user()),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'theme' => Theme::forRequest($request)->value,
        ];
    }

    /**
     * The signed-in user's effective permission names.
     *
     * A super admin gets `['*']` rather than the whole catalogue — the
     * `Gate::before` bypass means the list would be meaningless anyway, and
     * `useCan()` reads the wildcard. Hiding UI is not authorization; the route
     * middleware and policies are.
     *
     * @return list<string>
     */
    protected function permissionsFor(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return ['*'];
        }

        return array_values(array_filter(
            $user->getAllPermissions()->pluck('name')->all(),
            is_string(...),
        ));
    }
}
