<?php

namespace App\Http\Controllers\Merchandising;

use App\DataTransferObjects\Merchandising\BqsImportResult;
use App\DataTransferObjects\Merchandising\BqsResolveResult;
use App\Enums\Merchandising\BqsConflictDecision;
use App\Exceptions\Merchandising\BqsImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\BqsImportRequest;
use App\Http\Requests\Merchandising\BqsResolveRequest;
use App\Models\Merchandising\BqsImport;
use App\Services\Merchandising\BqsImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Uploads a buyer's BQS workbook and imports the buy plan in it.
 *
 * **There is no `create`.** The form is a modal on the list page — the pattern every
 * surface in this application follows (ARCHITECTURE.md §5) — and it asks for three
 * things: the buyer, the BQS date, and the file. The first two cannot be read from the
 * workbook. The buyer is chosen so an import cannot land somewhere the uploader cannot
 * see it; the date is chosen because the workbook carries no date at all.
 *
 * Reading runs **inside the request**, as the purchase-order import does, bounded by
 * `config('bqs-import.limits')`. PhpSpreadsheet loads the sheet in-process with no
 * external binary, so this is a cheaper request than a `.pdf` purchase order.
 *
 * An upload ends in one of two places. If its rows overlap nothing held, it is
 * finished. If they overlap a BQS already held, the whole workbook is **staged** and
 * {@see self::resolve()} takes the uploader's answer — one decision, not one per row,
 * because a workbook is one BQS.
 */
class BqsImportController extends Controller
{
    public function __construct(protected BqsImportService $imports) {}

    /**
     * Read an uploaded workbook and store the BQS it holds.
     */
    public function store(BqsImportRequest $request): RedirectResponse
    {
        try {
            $result = $this->imports->import(
                $request->file('file'),
                $request->integer('buyer_id'),
                $request->date('bqs_date')->toDateString(),
            );
        } catch (BqsImportException $exception) {
            /*
             * `error` rather than `warning` (ARCHITECTURE.md §8.8): the workbook is
             * not one this reader can use, and no amount of work by the actor on other
             * records changes that. Every message names the thing to fix — the missing
             * column, the duplicated row — and is written for whoever uploaded it.
             */
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', $this->toastFor($result));

        /*
         * Back to the list either way. When the workbook was staged, the list arrives
         * carrying `pendingImport` and the dialog reopens on its conflict step; the
         * redirect is explicit rather than `back()` so that behaviour does not depend
         * on a referer being present.
         */
        return $result->storedNothing() && ! $result->needsDecision()
            ? back()
            : to_route('merchandising.bqs.index');
    }

    /**
     * Apply the uploader's decision to the BQS this import held back.
     *
     * **Only the uploader may answer.** `BqsImport` is buyer-scoped, so another
     * buyer's import already 404s; this narrows it further to the person who chose the
     * file, for the reason {@see BqsImportService::pendingFor()} records.
     */
    public function resolve(BqsResolveRequest $request, BqsImport $bqsImport): RedirectResponse
    {
        abort_unless($bqsImport->inserted_by === $request->user()?->id, 404);

        $result = $this->imports->resolve($bqsImport, $request->decision());

        Inertia::flash('toast', $this->toastForResolution($result));

        return to_route('merchandising.bqs.index');
    }

    /**
     * Describe what the upload did.
     *
     * Severity follows §8.8: anything refused or waiting is a `warning`, because the
     * actor can clear it themselves — by uploading a different file, or by answering
     * the question the dialog is now holding.
     *
     * @return array{type: string, message: string}
     */
    private function toastFor(BqsImportResult $result): array
    {
        if ($result->isDuplicate) {
            return [
                'type' => 'warning',
                'message' => __('Nothing imported — :file has already been imported, unchanged.', [
                    'file' => $result->import->source_file_name,
                ]),
            ];
        }

        if ($result->needsDecision()) {
            return [
                'type' => 'warning',
                'message' => __('":file" overlaps :title, which is already on file. Your decision is needed.', [
                    'file' => $result->import->source_file_name,
                    'title' => $result->collidesWith ?? __('an existing BQS'),
                ]),
            ];
        }

        return [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Imported :file — 1 row.|[2,*]Imported :file — :count rows.',
                $result->rowCount(),
                ['file' => $result->import->source_file_name, 'count' => $result->rowCount()],
            ),
        ];
    }

    /**
     * Describe what the decision did.
     *
     * @return array{type: string, message: string}
     */
    private function toastForResolution(BqsResolveResult $result): array
    {
        $title = $result->title ?? __('the BQS');

        return match ($result->decision) {
            BqsConflictDecision::Skip => [
                'type' => 'info',
                'message' => __('Skipped. :title is unchanged.', ['title' => $title]),
            ],
            BqsConflictDecision::Revise => [
                'type' => 'success',
                'message' => __('Stored as revision :revision of :title.', [
                    'revision' => $result->revisionNo(),
                    'title' => $title,
                ]),
            ],
            BqsConflictDecision::Overwrite => [
                'type' => 'success',
                'message' => __('Replaced revision :revision of :title.', [
                    'revision' => $result->revisionNo(),
                    'title' => $title,
                ]),
            ],
        };
    }
}
