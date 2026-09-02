<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationColorIndexRequest;
use App\Http\Requests\Settings\NotificationColorStoreRequest;
use App\Http\Requests\Settings\NotificationColorUpdateRequest;
use App\Models\Settings\NotificationColor;
use App\Services\Settings\NotificationColorService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Settings module's first master-data surface.
 *
 * One page with modals — the `admin/designations` shape, not the
 * `admin/roles` index/create/edit shape (ARCHITECTURE.md §5).
 *
 * There is no policy: nothing about *which* colour is being edited changes who
 * may edit it, so the four `settings.master-data.*` permissions on the routes
 * are the whole authorization story.
 */
class NotificationColorController extends Controller
{
    public function __construct(protected NotificationColorService $notificationColors) {}

    /**
     * List every notification colour.
     */
    public function index(NotificationColorIndexRequest $request): Response
    {
        $filters = $request->filters();

        $notificationColors = NotificationColor::query()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (NotificationColor $color): array => [
                'id' => $color->id,
                'name' => $color->name,
                'color_code' => $color->color_code,
                'retention_days' => $color->retention_days,
                'status' => $color->status->value,
            ]);

        return Inertia::render('settings/master-data/notification-colors/index', [
            'notificationColors' => $notificationColors,
            'statuses' => RecordStatus::options(),
            'sortable' => NotificationColor::SORTABLE,
            'filterable' => NotificationColor::FILTERABLE,
            'perPageOptions' => NotificationColorIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created notification colour.
     */
    public function store(NotificationColorStoreRequest $request): RedirectResponse
    {
        $this->notificationColors->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification colour created.')]);

        return back();
    }

    /**
     * Update the given notification colour.
     */
    public function update(NotificationColorUpdateRequest $request, NotificationColor $notificationColor): RedirectResponse
    {
        $this->notificationColors->update($notificationColor, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification colour updated.')]);

        return back();
    }

    /**
     * Delete the given notification colour.
     *
     * Refuses when a TNA template paints a milestone with it — the blocker-then-warn
     * shape `DesignationController::destroy` uses. See {@see NotificationColorService}
     * for why that guard belongs in the service and not in a policy.
     */
    public function destroy(NotificationColor $notificationColor): RedirectResponse
    {
        $blocker = $this->notificationColors->deletionBlocker($notificationColor);

        if ($blocker !== null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $blocker]);

            return back();
        }

        $this->notificationColors->delete($notificationColor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification colour deleted.')]);

        return back();
    }
}
