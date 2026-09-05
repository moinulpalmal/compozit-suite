<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogHistoryRequest;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\Admin\AuditLog;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit browser — ARCHITECTURE.md §9.3.
 *
 * **Read-only, and there is no write action to add.** There is no `store`,
 * `update` or `destroy` here, and no `admin.audit-logs.{create,update,delete}`
 * permission for one to hide behind: a trail an administrator can edit answers
 * nothing. `admin.audit-logs.view` is the whole surface.
 *
 * One page with modals, the Admin shape (§5 Module 1): the list, a diff dialog,
 * and a record-history dialog.
 */
class AuditLogController extends Controller
{
    public function __construct(protected AuditLogService $audits) {}

    /**
     * List the trail, newest first.
     *
     * **Old and new values ride with each row**, so the diff dialog opens with no
     * request of its own. That is affordable only because the six payload columns
     * are excluded from auditing — without those exclusions a page of a hundred
     * import updates would be megabytes of props. If a fat column is ever audited,
     * this has to become a fetch.
     */
    public function index(AuditLogIndexRequest $request): Response
    {
        $filters = $request->filters();

        $page = AuditLog::query()
            ->filterColumns($filters['filter'])
            ->sortBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        /*
         * The names are resolved for the whole page *before* `through()` maps it,
         * so the per-row shaping stays a pure function and the page still costs
         * one query for every user id it mentions rather than two per row.
         */
        $names = $this->audits->actorNamesFor($page->getCollection());

        return Inertia::render('admin/audit-logs/index', [
            'auditLogs' => $page->through(
                fn (AuditLog $audit): array => $this->audits->describe($audit, $names),
            ),
            'events' => AuditEvent::options(),
            'modelTypes' => $this->audits->modelTypes(),
            'sortable' => AuditLog::SORTABLE,
            'filterable' => AuditLog::FILTERABLE,
            'perPageOptions' => AuditLogIndexRequest::PER_PAGE_OPTIONS,
            'filters' => $filters,
        ]);
    }

    /**
     * Every audit for one record, for the history dialog.
     *
     * A plain JSON read with no form behind it, so the page fetches it with
     * `fetch` and an `AbortController` rather than `useHttp` — ARCHITECTURE.md
     * §8.4.
     *
     * **The response carries no exception detail.** The implementation this was
     * ported from caught `\Exception` here and returned `message`, `file` and
     * `line` to the browser, which its own view then rendered into an alert. An
     * unhandled failure is Laravel's to report, and it reports it without handing
     * the client a filesystem path.
     */
    public function history(AuditLogHistoryRequest $request): JsonResponse
    {
        return response()->json([
            'audits' => $this->audits->historyFor($request->type(), $request->recordId()),
        ]);
    }
}
