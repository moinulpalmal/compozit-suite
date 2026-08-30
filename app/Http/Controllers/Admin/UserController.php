<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\Gender;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserBuyerAccessRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserPasswordUpdateRequest;
use App\Http\Requests\Admin\UserRoleUpdateRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Services\Admin\BuyerAccessService;
use App\Services\Admin\BuyerService;
use App\Services\Admin\DesignationService;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected UserService $users,
        protected DesignationService $designations,
        protected BuyerService $buyers,
        protected BuyerAccessService $buyerAccess,
    ) {}

    /**
     * List users, either the active ones or the soft-deleted history.
     *
     * Both live on one page behind a `view` query parameter rather than two
     * routes — see ARCHITECTURE.md → "Module 1 — Admin". That parameter selects
     * the record set; everything that filters a column value goes through the
     * filter row instead.
     */
    public function index(UserIndexRequest $request): Response
    {
        $filters = $request->filters();

        /*
         * Buyer access rides along only for those allowed to see it. It is one
         * extra query for the whole page — `buyers` is a small, bounded set and
         * an all-access user has no rows at all — which is why the access dialog
         * needs no endpoint of its own to open with the right state.
         */
        $showsBuyers = $request->user()?->can('admin.buyer-access.view') ?? false;

        $users = User::query()
            ->when($filters['view'] === 'trashed', fn ($query) => $query->onlyTrashed())
            ->with(['roles:id,name', 'designation:id,name,short_form', 'insertedBy:id,name', 'lastUpdatedBy:id,name'])
            ->when($showsBuyers, fn ($query) => $query->with('buyers:id,name,code')->withCount('buyers'))
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (User $user): array => $this->describe($user, $showsBuyers));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $this->users->assignableRoleNames(),
            'genders' => Gender::options(),
            'statuses' => RecordStatus::options(),
            /*
             * Two different lists on purpose. The edit modal may only offer
             * active designations — plus whichever retired ones this page's
             * rows already hold, so nobody's title is silently blanked. The
             * filter, by contrast, lists every designation: a deactivated
             * title still has holders and an admin has to be able to find them.
             */
            'designations' => $this->designations->assignableOptions(
                $users->getCollection()->pluck('designation_id')->all(),
            ),
            'designationFilters' => $this->designations->filterOptions(),
            'sortable' => User::SORTABLE,
            'filterable' => User::FILTERABLE,
            'perPageOptions' => UserIndexRequest::PER_PAGE_OPTIONS,
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
        $blocker = $this->users->statusBlocker(
            $user,
            RecordStatus::from($request->string('status')->value()),
        );

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
     * Replace the given user's buyer access.
     *
     * The only write path for this fact — there is no buyer-access page; see
     * ARCHITECTURE.md §9.2. Granting all-access clears the pivot, so the flag
     * and the rows can never disagree about what somebody can see.
     */
    public function updateBuyerAccess(UserBuyerAccessRequest $request, User $user): RedirectResponse
    {
        $allBuyers = $request->grantsAllBuyers();
        $buyers = $request->submittedBuyers();

        $blocker = $this->buyerAccess->assignmentBlocker($user, $allBuyers, $buyers);

        if ($blocker !== null) {
            return $this->refuse($blocker);
        }

        $this->buyerAccess->assign($user, $allBuyers, $buyers);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Buyer access updated.')]);

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
     * and on the target's current state, so they surface as a toast rather than
     * a 403.
     *
     * They stay `error` rather than `warning`: every blocker here — your own
     * account, the last super admin — is a rule the actor cannot work around,
     * unlike `DesignationController::destroy`, where reassigning the holders
     * clears the refusal.
     */
    protected function refuse(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }

    /**
     * Shape a user for the index table.
     *
     * `$withBuyers` mirrors the eager load in `index()`: without the permission
     * the relation was never loaded, and shipping `buyers_count` from an absent
     * relation would be a lie rather than a zero.
     *
     * @return array<string, mixed>
     */
    protected function describe(User $user, bool $withBuyers = false): array
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
            'designation_id' => $user->designation_id,
            'designation' => $user->designation?->name,
            'status' => $user->status->value,
            'approval_authority' => $user->approval_authority,
            'roles' => $user->roles->pluck('name'),
            'inserted_by' => $user->insertedBy?->name,
            'last_updated_by' => $user->lastUpdatedBy?->name,
            'created_at' => $user->created_at?->toDateString(),
            'deleted_at' => $user->deleted_at?->toDateString(),
            'is_self' => $this->users->isActor($user),
            'is_last_super_admin' => $this->users->isLastSuperAdmin($user),
            ...$withBuyers ? [
                'all_buyer_access' => $user->all_buyer_access,
                'buyers_count' => (int) ($user->buyers_count ?? 0),
                'buyers' => $this->buyers->describeHeld($user->buyers),
            ] : [],
        ];
    }
}
