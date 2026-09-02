<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Merchandising\TnaMilestone;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TnaTemplateIndexRequest;
use App\Http\Requests\Settings\TnaTemplateStoreRequest;
use App\Http\Requests\Settings\TnaTemplateUpdateRequest;
use App\Models\Settings\TnaTemplate;
use App\Models\Settings\TnaTemplateColor;
use App\Models\Settings\TnaTemplateMilestone;
use App\Services\Settings\NotificationColorService;
use App\Services\Settings\TnaTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The register of TNA schedules, under Settings' master data.
 *
 * One page with modals — the `admin/designations` shape, like every other master-data
 * surface (ARCHITECTURE.md §5).
 *
 * There is no policy, for the same reason {@see NotificationColorController} has
 * none: nothing about *which* template is being edited changes who may edit it, so
 * the four `settings.master-data.*` permissions on the routes are the whole
 * authorization story.
 */
class TnaTemplateController extends Controller
{
    public function __construct(
        protected TnaTemplateService $templates,
        protected NotificationColorService $notificationColors,
    ) {}

    /**
     * List every TNA template with its offsets and colour ladder.
     *
     * The children ride along with each row rather than being fetched when a modal
     * opens: a template is only editable as a whole, the register is small, and one
     * eager load beats a request per row (ARCHITECTURE.md §8.6).
     */
    public function index(TnaTemplateIndexRequest $request): Response
    {
        $filters = $request->filters();

        $templates = TnaTemplate::query()
            ->with(['milestones', 'colors.color'])
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (TnaTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'lead_time_from' => $template->lead_time_from,
                'lead_time_to' => $template->lead_time_to,
                'status' => $template->status->value,
                'milestones' => $template->milestones
                    ->map(fn (TnaTemplateMilestone $milestone): array => [
                        'milestone' => $milestone->milestone->value,
                        'label' => $milestone->milestone->label(),
                        'offset_days' => $milestone->offset_days,
                    ])->values()->all(),
                'colors' => $template->colors
                    ->map(fn (TnaTemplateColor $band): array => [
                        'notification_color_id' => $band->notification_color_id,
                        'max_days_remaining' => $band->max_days_remaining,
                        'name' => $band->color->name,
                        'color_code' => $band->color->color_code,
                    ])->values()->all(),
            ]);

        return Inertia::render('settings/master-data/tna-templates/index', [
            'templates' => $templates,
            'statuses' => RecordStatus::options(),
            /*
             * Only the schedulable milestones are offered. `Shipment` is read from
             * the purchase order, and a form that let someone type an offset for it
             * would be asking a question the write requests then refuse.
             */
            'milestoneOptions' => array_map(
                fn (TnaMilestone $milestone): array => [
                    'value' => $milestone->value,
                    'label' => $milestone->label(),
                ],
                TnaMilestone::planned(),
            ),
            /*
             * Every colour the ladder may use. `assignableOptions()` keeps a
             * deactivated colour visible to templates already holding it, so opening
             * a template that uses a retired colour shows it rather than blanking the
             * row on save.
             */
            'colorOptions' => $this->notificationColors->assignableOptions(
                $templates->getCollection()
                    ->flatMap(fn (array $row): array => array_column($row['colors'], 'notification_color_id'))
                    ->all(),
            ),
            'sortable' => TnaTemplate::SORTABLE,
            'filterable' => TnaTemplate::FILTERABLE,
            'perPageOptions' => TnaTemplateIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created TNA template.
     */
    public function store(TnaTemplateStoreRequest $request): RedirectResponse
    {
        $this->templates->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TNA template created.')]);

        return back();
    }

    /**
     * Update the given TNA template.
     */
    public function update(TnaTemplateUpdateRequest $request, TnaTemplate $tnaTemplate): RedirectResponse
    {
        $this->templates->update($tnaTemplate, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TNA template updated.')]);

        return back();
    }

    /**
     * Delete the given TNA template.
     *
     * Unconditional, and that is not an oversight: nothing holds a foreign key to a
     * template. The TNA page matches one at read time and stores nothing, so deleting
     * one changes which schedule an order is drawn with and destroys no record. See
     * {@see TnaTemplateService} for the contrast with notification colours.
     */
    public function destroy(TnaTemplate $tnaTemplate): RedirectResponse
    {
        $this->templates->delete($tnaTemplate);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TNA template deleted.')]);

        return back();
    }
}
