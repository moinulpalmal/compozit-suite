<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DesignationIndexRequest;
use App\Http\Requests\Admin\DesignationStoreRequest;
use App\Http\Requests\Admin\DesignationUpdateRequest;
use App\Models\Admin\Designation;
use App\Services\Admin\DesignationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    /**
     * Rows per page on the designation list.
     */
    protected const int PER_PAGE = 25;

    public function __construct(protected DesignationService $designations) {}

    /**
     * List every designation.
     *
     * One page: create, edit and delete all happen in modals, the way
     * `admin/users` works. There are no Active/Historical tabs here — a
     * designation is retired by setting its status, not by soft-deleting it.
     */
    public function index(DesignationIndexRequest $request): Response
    {
        $filters = $request->filters();

        $designations = Designation::query()
            ->withCount(['users as users_count' => fn (Builder $query) => $query->withTrashed()])
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->search($filters['search_field'], $filters['search'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Designation $designation): array => [
                'id' => $designation->id,
                'name' => $designation->name,
                'short_form' => $designation->short_form,
                'status' => $designation->status->value,
                'users_count' => (int) $designation->users_count,
                'is_deletable' => $designation->users_count === 0,
            ]);

        return Inertia::render('admin/designations/index', [
            'designations' => $designations,
            'statuses' => RecordStatus::options(),
            'sortable' => Designation::SORTABLE,
            'searchable' => Designation::SEARCHABLE,
            'filters' => $filters,
        ]);
    }

    /**
     * Search assignable designations for a combobox.
     *
     * The async source `<Combobox searchUrl>` reads. Returns `{data: [...]}` —
     * the shape every options endpoint uses, so one hook serves all of them.
     * See ARCHITECTURE.md §8.5.
     */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->designations->searchAssignable($validated['q'] ?? null),
        ]);
    }

    /**
     * Store a newly created designation.
     */
    public function store(DesignationStoreRequest $request): RedirectResponse
    {
        $this->designations->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Designation created.')]);

        return back();
    }

    /**
     * Update the given designation.
     */
    public function update(DesignationUpdateRequest $request, Designation $designation): RedirectResponse
    {
        $this->designations->update($designation, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Designation updated.')]);

        return back();
    }

    /**
     * Delete the given designation.
     *
     * Refused while anybody holds it. That refusal is a fact about the record,
     * not about the actor, so it surfaces as an error toast rather than a 403 —
     * the same shape as `UserController::refuse()`.
     */
    public function destroy(Designation $designation): RedirectResponse
    {
        $blocker = $this->designations->deletionBlocker($designation);

        if ($blocker !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blocker]);

            return back();
        }

        $this->designations->delete($designation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Designation deleted.')]);

        return back();
    }
}
