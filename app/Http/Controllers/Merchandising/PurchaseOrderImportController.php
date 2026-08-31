<?php

namespace App\Http\Controllers\Merchandising;

use App\DataTransferObjects\Merchandising\PoImportResult;
use App\Exceptions\Merchandising\PoParser\PoParserException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchandising\PurchaseOrderImportRequest;
use App\Services\Merchandising\PurchaseOrderImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uploads a buyer's purchase-order document and imports every order in it.
 *
 * Parsing runs **inside the request**. It shells out to LibreOffice for `.doc` and to
 * `pdftotext` for `.pdf`, each with its own timeout, and `po-parser.limits` bounds
 * what can be submitted — file size in the form request, page and order counts in the
 * parser. A document at those limits is the slow case; the alternative, a queued
 * import with a status page to poll, was weighed and declined for a first version
 * because it costs a table, a polling surface, and a hard dependency on a worker
 * being up. `documentation/merchandising.md` records the trade so it can be revisited
 * with evidence rather than from memory.
 */
class PurchaseOrderImportController extends Controller
{
    public function __construct(protected PurchaseOrderImportService $imports) {}

    /**
     * Show the import form.
     */
    public function create(): Response
    {
        return Inertia::render('merchandising/purchase-orders/import', [
            'buyers' => $this->imports->assignableBuyerOptions(),
            'acceptedExtensions' => config('po-parser.accepted_extensions'),
            'maxFileSizeKb' => (int) config('po-parser.limits.max_file_size_kb'),
        ]);
    }

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

        return $result->storedNothing()
            ? back()
            : to_route('merchandising.purchase-orders.index');
    }

    /**
     * Describe what the import did.
     *
     * A document holding several orders can land partly: some new, some a fresh
     * revision of an order already held, some refused as an identical re-upload. The
     * severity follows §8.8 — anything refused is a `warning`, because the actor can
     * clear it themselves by deleting what is already there.
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

        if ($result->revisedPoNumbers !== []) {
            return [
                'type' => 'success',
                'message' => __('Imported :count purchase orders, including new revisions of :numbers.', [
                    'count' => $stored,
                    'numbers' => implode(', ', $result->revisedPoNumbers),
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
}
