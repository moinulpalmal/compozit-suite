<?php

namespace App\Http\Controllers\Merchandising;

use App\DataTransferObjects\Merchandising\PoImportResult;
use App\DataTransferObjects\Merchandising\PoResolveResult;
use App\Exceptions\Merchandising\PoParser\PoParserException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\PurchaseOrderImportRequest;
use App\Http\Requests\Merchandising\PurchaseOrderResolveRequest;
use App\Models\Merchandising\PoImport;
use App\Services\Merchandising\PurchaseOrderImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Uploads a buyer's purchase-order document and imports every order in it.
 *
 * **There is no `create`.** The form is a modal on the list page — the pattern every
 * other surface in this application follows (ARCHITECTURE.md §5), and the standalone
 * page this replaced was the last exception to it. `PurchaseOrderController::index`
 * supplies the buyer options the dialog needs.
 *
 * Parsing runs **inside the request**. It shells out to LibreOffice for `.doc` and to
 * `pdftotext` for `.pdf`, each with its own timeout, and `po-parser.limits` bounds
 * what can be submitted — file size in the form request, page and order counts in the
 * parser. A document at those limits is the slow case; the alternative, a queued
 * import with a status page to poll, was weighed and declined for a first version
 * because it costs a table, a polling surface, and a hard dependency on a worker
 * being up. `documentation/merchandising.md` records the trade so it can be revisited
 * with evidence rather than from memory.
 *
 * An upload can end in one of two places. If nothing collided it is finished. If some
 * orders match one already held, those are **staged** and {@see self::resolve()} takes
 * the uploader's answer — a document holds up to fifty orders, which is why the
 * question cannot be asked inside the upload request.
 */
class PurchaseOrderImportController extends Controller
{
    public function __construct(protected PurchaseOrderImportService $imports) {}

    /**
     * Parse an uploaded document and store the purchase orders it holds.
     */
    public function store(PurchaseOrderImportRequest $request): RedirectResponse
    {
        try {
            $result = $this->imports->import($request->file('file'), $request->integer('buyer_id'));
        } catch (PoParserException $exception) {
            /*
             * `error` rather than `warning` (ARCHITECTURE.md §8.8): the document is
             * not one this parser can read, and no amount of work by the actor on
             * other records changes that. The parser's own messages name the cause —
             * a missing converter, a scanned PDF, a document with no Walmart pages —
             * and are written to be read by whoever uploaded the file.
             */
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', $this->toastFor($result));

        /*
         * Back to the list either way. When orders were staged, the list arrives
         * carrying `pendingImport` and the dialog reopens on its conflict step; the
         * redirect is explicit rather than `back()` so that behaviour does not depend
         * on a referer being present.
         */
        return $result->storedNothing()
            ? back()
            : to_route('merchandising.purchase-orders.index');
    }

    /**
     * Apply the uploader's decision to the orders an import held back.
     *
     * **Only the uploader may answer.** `PoImport` is buyer-scoped, so another buyer's
     * import already 404s; this narrows it further to the person who chose the file.
     * The alternative — anyone with the permission and the buyer — means a colleague
     * deciding "reissue or stale?" about a document they have not seen, and the honest
     * fallback for them is to upload it themselves.
     */
    public function resolve(PurchaseOrderResolveRequest $request, PoImport $poImport): RedirectResponse
    {
        abort_unless($poImport->inserted_by === $request->user()?->id, 404);

        $result = $this->imports->resolve($poImport, $request->decisions());

        Inertia::flash('toast', $this->toastForResolution($result));

        return to_route('merchandising.purchase-orders.index');
    }

    /**
     * Describe what the upload did.
     *
     * A document holding several orders can land partly: some new, some refused as an
     * identical re-upload, some held back for a decision. The severity follows §8.8 —
     * anything refused or waiting is a `warning`, because the actor can clear it
     * themselves.
     *
     * @return array{type: string, message: string}
     */
    private function toastFor(PoImportResult $result): array
    {
        $stored = $result->storedCount();
        $duplicates = count($result->duplicatePoNumbers);

        if ($result->storedNothing()) {
            return [
                'type' => 'warning',
                'message' => trans_choice(
                    '{1}Nothing imported — purchase order :numbers is already imported.'
                    .'|[2,*]Nothing imported — purchase orders :numbers are already imported.',
                    $duplicates,
                    ['numbers' => implode(', ', $result->duplicatePoNumbers)],
                ),
            ];
        }

        if ($result->needsDecisions()) {
            return [
                'type' => 'warning',
                'message' => trans_choice(
                    '{1}Imported :stored. Purchase order :numbers is already on file and needs your decision.'
                    .'|[2,*]Imported :stored. Purchase orders :numbers are already on file and need your decision.',
                    count($result->stagedPoNumbers),
                    [
                        'stored' => trans_choice(
                            '{0}nothing|{1}1 purchase order|[2,*]:count purchase orders',
                            $stored,
                            ['count' => $stored],
                        ),
                        'numbers' => implode(', ', $result->stagedPoNumbers),
                    ],
                ),
            ];
        }

        if ($result->hasDuplicates()) {
            return [
                'type' => 'warning',
                'message' => __('Imported :stored of :total purchase orders. :numbers were already imported.', [
                    'stored' => $stored,
                    'total' => $stored + $duplicates,
                    'numbers' => implode(', ', $result->duplicatePoNumbers),
                ]),
            ];
        }

        return [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Imported 1 purchase order.|[2,*]Imported :count purchase orders.',
                $stored,
                ['count' => $stored],
            ),
        ];
    }

    /**
     * Describe what the decision did.
     *
     * An overwrite destroyed a stored order, so the message names it rather than
     * reporting a count — this is the one outcome here nothing can undo.
     *
     * @return array{type: string, message: string}
     */
    private function toastForResolution(PoResolveResult $result): array
    {
        if ($result->changedNothing()) {
            return [
                'type' => 'info',
                'message' => trans_choice(
                    '{0}Nothing was changed.'
                    .'|{1}Skipped 1 purchase order. Nothing was changed.'
                    .'|[2,*]Skipped :count purchase orders. Nothing was changed.',
                    count($result->skippedPoNumbers),
                    ['count' => count($result->skippedPoNumbers)],
                ),
            ];
        }

        if ($result->hasOverwrites()) {
            $message = trans_choice(
                '{1}Overwrote purchase order :numbers. The previous version is gone.'
                .'|[2,*]Overwrote purchase orders :numbers. The previous versions are gone.',
                count($result->overwrittenPoNumbers),
                ['numbers' => implode(', ', $result->overwrittenPoNumbers)],
            );

            // A mixed decision still has to report the revisions, or the count the
            // user sees on the list will not match what they were told.
            if ($result->revisedPoNumbers !== []) {
                $message .= ' '.__('Revised :numbers.', [
                    'numbers' => implode(', ', $result->revisedPoNumbers),
                ]);
            }

            return ['type' => 'warning', 'message' => $message];
        }

        return [
            'type' => 'success',
            'message' => trans_choice(
                '{1}Stored a new revision of purchase order :numbers.'
                .'|[2,*]Stored new revisions of purchase orders :numbers.',
                count($result->revisedPoNumbers),
                ['numbers' => implode(', ', $result->revisedPoNumbers)],
            ),
        ];
    }
}
