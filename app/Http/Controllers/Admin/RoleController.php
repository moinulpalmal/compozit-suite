<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Models\Admin\Role;
use App\Services\Admin\PermissionService;
use App\Services\Admin\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roles,
        protected PermissionService $permissions,
    ) {}

    /**
     * List every role.
     */
    public function index(RoleIndexRequest $request): Response
    {
        $filters = $request->filters();

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_super_admin' => $role->isSuperAdmin(),
                'is_deletable' => $this->roles->isDeletable($role),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'sortable' => Role::SORTABLE,
            'filterable' => Role::FILTERABLE,
            'perPageOptions' => RoleIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a role.
     */
    public function create(): Response
    {
        return Inertia::render('admin/roles/create', [
            'permissions' => $this->permissions->groupedByModule(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $this->roles->create(
            $request->string('name')->value(),
            $request->submittedPermissions(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Role $role): Response
    {
        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_super_admin' => $role->isSuperAdmin(),
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissions' => $this->permissions->groupedByModule(),
        ]);
    }

    /**
     * Update the given role.
     */
    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->isSuperAdmin(), 403, __('The super-admin role cannot be modified.'));

        $this->roles->update(
            $role,
            $request->string('name')->value(),
            $request->submittedPermissions(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.index');
    }

    /**
     * Delete the given role.
     *
     * The two refusals are different severities. A role still held by users is a
     * *warning* — the actor can reassign them and try again. The super-admin role
     * is an *error*: no amount of work by the actor makes it deletable.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if (! $this->roles->isDeletable($role)) {
            Inertia::flash('toast', $role->isSuperAdmin()
                ? ['type' => 'error', 'message' => __('The super-admin role cannot be deleted.')]
                : ['type' => 'warning', 'message' => __('This role is still assigned to users.')]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }
}
