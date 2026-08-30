<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuyerIndexRequest;
use App\Http\Requests\Admin\BuyerStoreRequest;
use App\Http\Requests\Admin\BuyerUpdateRequest;
use App\Models\Admin\Buyer;
use App\Services\Admin\BuyerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
    public function __construct(protected BuyerService $buyers) {}

    /**
     * List every buyer.
     *
     * One page: create, edit and delete all happen in modals, the way
     * `admin/designations` works. There are no Active/Historical tabs — a buyer
     * is retired by setting its status, not by soft-deleting it.
     */
    public function index(BuyerIndexRequest $request): Response
    {
        $filters = $request->filters();

        $buyers = Buyer::query()
            ->withCount('users')
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Buyer $buyer): array => [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'code' => $buyer->code,
                'status' => $buyer->status->value,
                /*
                 * Users holding `all_buyer_access` are not counted — they have no
                 * pivot row and see this buyer anyway. The column is labelled
                 * "Granted" rather than "Users" so it does not read as the
                 * complete answer. See ARCHITECTURE.md §9.2.
                 */
                'users_count' => (int) $buyer->users_count,
            ]);

        return Inertia::render('admin/buyers/index', [
            'buyers' => $buyers,
            'statuses' => RecordStatus::options(),
            'sortable' => Buyer::SORTABLE,
            'filterable' => Buyer::FILTERABLE,
            'perPageOptions' => BuyerIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Search assignable buyers for a combobox.
     *
     * The async source `<Combobox searchUrl>` reads, in the same `{data: [...]}`
     * shape as `admin.designations.options` so one hook serves both. Capped and
     * prefix-matched — see ARCHITECTURE.md §8.5.
     */
    public function options(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->buyers->searchAssignable($validated['q'] ?? null),
        ]);
    }

    /**
     * Store a newly created buyer.
     */
    public function store(BuyerStoreRequest $request): RedirectResponse
    {
        $this->buyers->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Buyer created.')]);

        return back();
    }

    /**
     * Update the given buyer.
     */
    public function update(BuyerUpdateRequest $request, Buyer $buyer): RedirectResponse
    {
        $this->buyers->update($buyer, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Buyer updated.')]);

        return back();
    }

    /**
     * Delete the given buyer.
     *
     * Its access grants go with it — the foreign key cascades, because a grant
     * is a derived permission rather than history. Anything factual about the
     * buyer refuses the delete instead, as a *warning*: the actor can clear it
     * themselves, which is the distinction `DesignationController::destroy`
     * draws against `UserController`'s hard `error` refusals (ARCHITECTURE.md §8.8).
     */
    public function destroy(Buyer $buyer): RedirectResponse
    {
        $blocker = $this->buyers->deletionBlocker($buyer);

        if ($blocker !== null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $blocker]);

            return back();
        }

        $this->buyers->delete($buyer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Buyer deleted.')]);

        return back();
    }
}
