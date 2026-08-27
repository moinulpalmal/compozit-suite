<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserPasswordUpdateRequest;
use App\Http\Requests\Admin\UserRoleUpdateRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Rows per page on the user list.
     */
    protected const int PER_PAGE = 25;

    public function __construct(protected UserService $users) {}

    /**
     * List users, either the active ones or the soft-deleted history.
     *
     * Both live on one page behind a `filter` query parameter rather than two
     * routes — see ARCHITECTURE.md → "Module 1 — Admin".
     */
    public function index(UserIndexRequest $request): Response
    {
        $filters = $request->filters();

        $users = User::query()
            ->when($filters['filter'] === 'trashed', fn ($query) => $query->onlyTrashed())
            ->when($filters['gender'] !== '', fn ($query) => $query->where('gender', $filters['gender']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('approved', $filters['status'] === 'active'))
            ->with(['roles:id,name', 'insertedBy:id,name', 'lastUpdatedBy:id,name'])
            ->search($filters['search_field'], $filters['search'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through($this->describe(...));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $this->users->assignableRoleNames(),
            'genders' => Gender::options(),
            'sortable' => User::SORTABLE,
            'searchable' => User::SEARCHABLE,
            'filters' => $filters,
            'counts' => [
                'active' => User::query()->count(),
                'trashed' => User::onlyTrashed()->count(),
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->users->create(
            $request->userAttributes(),
            $request->string('password')->value(),
            $request->submittedRoles(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    /**
     * Update the given user's profile and HR fields.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $blocker = $this->users->approvalBlocker($user, $request->boolean('approved'));

        if ($blocker !== null) {
            return $this->refuse($blocker);
        }

        $this->users->update($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    /**
     * Soft-delete the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $blocker = $this->users->deletionBlocker($user);

        if ($blocker !== null) {
            return $this->refuse($blocker);
        }

        $this->users->delete($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User moved to history.')]);

        return back();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(User $user): RedirectResponse
    {
        $this->users->restore($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User restored.')]);

        return back();
    }

    /**
     * Permanently delete the given user.
     */
    public function forceDelete(User $user): RedirectResponse
    {
        $blocker = $this->users->deletionBlocker($user);

        if ($blocker !== null) {
            return $this->refuse($blocker);
        }

        $this->users->forceDelete($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User permanently deleted.')]);

        return back();
    }

    /**
     * Set the given user's password.
     */
    public function updatePassword(UserPasswordUpdateRequest $request, User $user): RedirectResponse
    {
        $this->users->resetPassword($user, $request->string('password')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Replace the given user's roles.
     */
    public function updateRoles(UserRoleUpdateRequest $request, User $user): RedirectResponse
    {
        $roles = $request->submittedRoles();

        $blocker = $this->users->roleAssignmentBlocker($user, $roles);

        if ($blocker !== null) {
            return $this->refuse($blocker);
        }

        $this->users->assignRoles($user, $roles);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated.')]);

        return back();
    }

    /**
     * Report whether an employee ID or email is still free.
     *
     * Backs the live check in the user form. Soft-deleted rows count as taken,
     * because the database's unique index does not exclude them.
     */
    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:employee_id,email'],
            'value' => ['required', 'string', 'max:255'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $taken = User::withTrashed()
            ->where($validated['field'], $validated['value'])
            ->when($validated['ignore'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        return response()->json(['available' => ! $taken]);
    }

    /**
     * Flash a refusal and send the user back to the page they came from.
     *
     * Guard failures are not validation errors — they depend on who is asking
     * and on the target's current state, so they surface as an error toast the
     * same way `RoleController::destroy` reports an undeletable role.
     */
    protected function refuse(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }

    /**
     * Shape a user for the index table.
     *
     * @return array<string, mixed>
     */
    protected function describe(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'personal_mobile_no' => $user->personal_mobile_no,
            'official_mobile_no' => $user->official_mobile_no,
            'official_extension_no' => $user->official_extension_no,
            'gender' => $user->gender->value,
            'approved' => $user->approved,
            'approval_authority' => $user->approval_authority,
            'roles' => $user->roles->pluck('name'),
            'inserted_by' => $user->insertedBy?->name,
            'last_updated_by' => $user->lastUpdatedBy?->name,
            'created_at' => $user->created_at?->toDateString(),
            'deleted_at' => $user->deleted_at?->toDateString(),
            'is_self' => $this->users->isActor($user),
            'is_last_super_admin' => $this->users->isLastSuperAdmin($user),
        ];
    }
}
