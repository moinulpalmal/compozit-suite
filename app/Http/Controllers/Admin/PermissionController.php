<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionStoreRequest;
use App\Http\Requests\Admin\PermissionUpdateRequest;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Services\Admin\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(protected PermissionService $permissions) {}

    /**
     * List every permission, grouped by module.
     */
    public function index(): Response
    {
        $permissions = Permission::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'module' => $permission->module(),
                'roles' => $permission->roles->pluck('name'),
            ]);

        return Inertia::render('admin/permissions/index', ['permissions' => $permissions]);
    }

    /**
     * Show the form for creating a permission.
     */
    public function create(): Response
    {
        return Inertia::render('admin/permissions/create', ['roles' => $this->roleNames()]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(PermissionStoreRequest $request): RedirectResponse
    {
        $this->permissions->create(
            $request->string('name')->value(),
            $request->submittedRoles(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('admin.permissions.index');
    }

    /**
     * Show the form for editing a permission.
     */
    public function edit(Permission $permission): Response
    {
        return Inertia::render('admin/permissions/edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'roles' => $permission->roles->pluck('name'),
            ],
            'roles' => $this->roleNames(),
        ]);
    }

    /**
     * Update the given permission.
     */
    public function update(PermissionUpdateRequest $request, Permission $permission): RedirectResponse
    {
        $this->permissions->update(
            $permission,
            $request->string('name')->value(),
            $request->submittedRoles(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('admin.permissions.index');
    }

    /**
     * Delete the given permission.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('admin.permissions.index');
    }

    /**
     * Every assignable role name, super-admin excluded — it holds everything already.
     *
     * @return list<string>
     */
    protected function roleNames(): array
    {
        return array_values(array_filter(
            Role::query()
                ->whereNot('name', Role::SUPER_ADMIN)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            is_string(...),
        ));
    }
}
