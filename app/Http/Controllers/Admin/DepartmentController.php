<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentIndexRequest;
use App\Http\Requests\Admin\DepartmentStoreRequest;
use App\Http\Requests\Admin\DepartmentUpdateRequest;
use App\Models\Admin\Department;
use App\Services\Admin\BuyerService;
use App\Services\Admin\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $departments,
        protected BuyerService $buyers,
    ) {}

    /**
     * List the departments of every buyer the actor may see.
     *
     * One page: create, edit and delete all happen in modals, the way
     * `admin/designations` works. A department is retired by setting its status,
     * not by soft-deleting it, so there are no Active/Historical tabs.
     *
     * **There is no `options` endpoint and no async combobox here.** The only
     * picker this surface needs is the buyer one, and an actor's buyer set is
     * short by construction, so it ships whole as a prop — which still honours
     * "a list and its picker are different queries" (ARCHITECTURE.md §8.6), with
     * one fewer route to gate.
     */
    public function index(DepartmentIndexRequest $request): Response
    {
        $filters = $request->filters();

        $departments = Department::query()
            ->with('buyer:id,name')
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Department $department): array => [
                'id' => $department->id,
                'buyer_id' => $department->buyer_id,
                'buyer' => $department->buyer->name,
                'name' => $department->name,
                'code' => $department->code,
                'status' => $department->status->value,
            ]);

        /*
         * Two different buyer sets, and conflating them is the bug this comment
         * exists to prevent. The form may only offer *active* buyers the actor
         * holds; the filter must offer every buyer they hold, because a retired
         * buyer still has departments somebody has to be able to find.
         */
        return Inertia::render('admin/departments/index', [
            'departments' => $departments,
            'buyers' => $this->buyers->assignableOptions(),
            'buyerFilterOptions' => $this->buyers->filterOptions(),
            'hasBuyerAccess' => $this->buyers->filterOptionsForActor() !== [],
            'statuses' => RecordStatus::options(),
            'sortable' => Department::SORTABLE,
            'filterable' => Department::FILTERABLE,
            'perPageOptions' => DepartmentIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(DepartmentStoreRequest $request): RedirectResponse
    {
        $this->departments->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return back();
    }

    /**
     * Update the given department.
     */
    public function update(DepartmentUpdateRequest $request, Department $department): RedirectResponse
    {
        $this->departments->update($department, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return back();
    }

    /**
     * Delete the given department.
     *
     * The blocker returns nothing today — nothing references a department yet —
     * but the call stays, so wiring the real check is one clause in the service
     * rather than a change here as well. See
     * {@see DepartmentService::deletionBlocker()}.
     *
     * A refusal is a fact about the record, not about the actor, so it surfaces
     * as a warning toast rather than a 403 — the shape `DesignationController`
     * uses.
     */
    public function destroy(Department $department): RedirectResponse
    {
        $blocker = $this->departments->deletionBlocker($department);

        if ($blocker !== null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $blocker]);

            return back();
        }

        $this->departments->delete($department);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return back();
    }
}
