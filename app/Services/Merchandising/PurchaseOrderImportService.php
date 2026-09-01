<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\Po\ParseResultDto;
use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\DataTransferObjects\Merchandising\PoImportResult;
use App\DataTransferObjects\Merchandising\PoResolveResult;
use App\Enums\Merchandising\PoConflictDecision;
use App\Enums\Merchandising\PoType;
use App\Exceptions\Merchandising\PoParser\PoParserException;
use App\Exceptions\Merchandising\PoParser\TextExtractionException;
use App\Http\Requests\Merchandising\PurchaseOrderImportRequest;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\User;
use App\Services\Admin\BuyerService;
use App\Services\Merchandising\PoParser\ParserService;
use App\Services\Merchandising\PoParser\Support\ParseGrader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded purchase-order document and everything the parser read out of it.
 *
 * The order of operations matters and is deliberate:
 *
 * 1. **Store the file, then parse, both outside any transaction.** Parsing shells out
 *    to LibreOffice or `pdftotext` and is allowed up to sixty seconds; holding a
 *    database transaction open across that would pin a connection and lock rows for
 *    the duration.
 * 2. **Write everything inside one transaction.** A document holding three orders
 *    either lands as three orders or as none — a half-imported file is the one state
 *    with no clean recovery.
 *
 * ## Duplicates, revisions, and the question only a human can answer
 *
 * Walmart reissues orders, and each document states its own revision
 * (`Revised Date … By:`). A purchase order already held is matched two ways:
 *
 * - the same `source_hash` — a byte-identical re-upload. **Skipped silently**: nothing
 *   changed, so there is nothing to ask about.
 * - the same PO number with **different content**. This the parser cannot judge. A
 *   genuine reissue and someone re-uploading a stale document look exactly alike, and
 *   only the person holding the file knows which it is — so it is **staged**, not
 *   written, and the uploader is asked. See {@see self::resolve()}.
 *
 * **A document whose orders are partly duplicates imports the rest**, and a document
 * whose orders partly collide imports the ones that do not. Refusing three orders
 * because the second was already held would lose two good ones to protect nothing; the
 * controller reports every count, and {@see PoImportResult} carries them.
 *
 * ## Why the rows are staged and not the parse
 *
 * A document holds up to fifty orders, so the conflicts cannot be resolved inside the
 * upload request. What survives to the second request is the **insertable row**
 * ({@see self::orderAttributes()}), not the parse: no DTO here can be rebuilt from an
 * array, and confirming must not cost a second run through LibreOffice.
 *
 * That makes one rule load-bearing: **`orderAttributes()` returns scalars and arrays
 * only** — no enum instances, no `Carbon` — so a row that has been through JSON is
 * identical to one that has not. `PurchaseOrderResolveTest` pins it.
 *
 * @phpstan-type StagedOrder array{
 *     po_number: string,
 *     source_hash: string,
 *     held: array<string, mixed>|null,
 *     incoming: array<string, mixed>,
 *     attributes: array<string, mixed>,
 *     line_items: list<array<string, mixed>>,
 * }
 */
final class PurchaseOrderImportService
{
    public function __construct(
        private readonly ParserService $parser,
        private readonly ParseGrader $grader,
        private readonly BuyerService $buyers,
        private readonly BqsPoLinker $linker,
    ) {}

    /**
     * The buyers this user may import for: active, and within their own access.
     *
     * **The query moved to {@see BuyerService::assignableToActor()}** when the BQS
     * import became a second surface asking the identical question; the reasoning is
     * recorded there. This method and its name stay so that every existing caller —
     * the import request's `Rule::in`, the list controller's dialog props — is
     * unchanged, and so a future divergence between the two importers has one obvious
     * place to happen rather than two silent ones.
     *
     * @return array<int, string> buyer id => name
     */
    public function assignableBuyers(): array
    {
        return $this->buyers->assignableToActor();
    }

    /**
     * The same set, shaped for `components/ui/combobox.tsx`.
     *
     * @return list<array{value: int, label: string}>
     */
    public function assignableBuyerOptions(): array
    {
        return $this->buyers->assignableOptions();
    }

    /**
     * Parse an uploaded document and store every purchase order it holds.
     *
     * @throws PoParserException when the
     *                           document cannot be read or holds no purchase orders
     */
    public function import(UploadedFile $file, int $buyerId): PoImportResult
    {
        $disk = config('po-parser.storage.disk');
        $storedPath = $file->store(config('po-parser.storage.upload'), $disk);

        /*
         * `store()` answers `false` when the disk refuses the write — a full or
         * unwritable volume, most often. Left unchecked that `false` travels into
         * `path()` and the parser is handed the disk root, which fails much later
         * with a message about the wrong thing entirely.
         */
        if ($storedPath === false) {
            throw new TextExtractionException(
                'The uploaded document could not be stored on the "'.$disk.'" disk. Check that it exists and is writable.'
            );
        }

        $absolutePath = Storage::disk($disk)->path($storedPath);

        try {
            $result = $this->parser->parse($absolutePath, $file->getClientOriginalName());
        } catch (\Throwable $exception) {
            // Nothing was written, so the stored upload would be an orphan.
            Storage::disk($disk)->delete($storedPath);

            throw $exception;
        }

        if (! config('po-parser.storage.retain_original')) {
            Storage::disk($disk)->delete($storedPath);
            $storedPath = null;
        }

        return DB::transaction(fn (): PoImportResult => $this->store($result, $buyerId, $storedPath));
    }

    private function store(ParseResultDto $result, int $buyerId, ?string $storedPath): PoImportResult
    {
        $import = PoImport::create([
            'buyer_id' => $buyerId,
            'source_file_name' => $result->sourceFileName,
            'stored_path' => $storedPath,
            'detected_file_type' => $result->detectedFileType,
            'template_fingerprint' => $result->templateFingerprint,
            'page_count' => $result->pageCount,
            'po_count' => $result->poCount,
            'parse_status' => $result->status,
            'confidence' => $result->overallConfidence,
            'payload' => $result->toArray(),
        ]);

        $imported = [];
        $staged = [];
        $duplicates = [];

        foreach ($result->purchaseOrders as $po) {
            $poNumber = (string) $po->header?->poNumber;
            $sourceHash = $this->sourceHash($po);

            $existing = $this->existingRevisions($buyerId, $poNumber);

            if ($existing->contains('source_hash', $sourceHash)) {
                $duplicates[] = $poNumber;

                continue;
            }

            $candidate = $this->stagedOrder($import, $po, $buyerId, $poNumber, $sourceHash, $existing);

            /*
             * Nothing held under this number, so there is no question to ask and no
             * reason to make the uploader confirm an order they have never seen.
             * It lands now, at revision 1.
             */
            if ($candidate['held'] === null) {
                $this->writeOrder($import, $candidate, revisionNo: 1);
                $imported[] = $poNumber;

                continue;
            }

            $staged[] = $candidate;
        }

        if ($staged !== []) {
            $import->update(['staged_orders' => $staged]);
        }

        return new PoImportResult(
            import: $import,
            importedPoNumbers: $imported,
            duplicatePoNumbers: $duplicates,
            stagedPoNumbers: array_map(
                fn (array $conflict): string => (string) $conflict['po_number'],
                $staged,
            ),
        );
    }

    /**
     * Everything needed to write one order, plus what a human needs to decide about it.
     *
     * The same shape is used whether the order lands immediately or waits: the clean
     * path and the resolve path write through {@see self::writeOrder()} with identical
     * input, which is what makes them impossible to drift apart.
     *
     * `held` is `null` when nothing is stored under this number — that is the signal
     * there is no conflict, rather than a second flag that could disagree with it.
     *
     * @param  Collection<int, PurchaseOrder>  $existing
     * @return StagedOrder
     */
    private function stagedOrder(
        PoImport $import,
        PurchaseOrderDto $po,
        int $buyerId,
        string $poNumber,
        string $sourceHash,
        Collection $existing,
    ): array {
        $lineItems = $this->lineItemRows($po);

        return [
            'po_number' => $poNumber,
            'source_hash' => $sourceHash,
            'held' => $this->heldSummary($existing),
            'incoming' => [
                // Formatted to match `held`, which comes back through a datetime
                // cast — the two are read side by side and `2026-07-06T20:35:01`
                // against `2026-07-06 20:35:01` reads as a difference that is not
                // one. The raw value is untouched in `attributes`.
                'revised_at' => $this->displayDate($po->header?->revisedDate),
                'revised_by' => $po->header?->revisedBy,
                'total_qty' => $po->summary?->quantityEa,
                'line_item_count' => count($lineItems),
            ],
            'attributes' => $this->orderAttributes($po, $buyerId, $poNumber, $sourceHash, $import->template_fingerprint),
            'line_items' => $lineItems,
        ];
    }

    /**
     * A parsed date string as the conflict rows show it, or null if it is not one.
     *
     * The parser hands back whatever the document printed, and a document missing
     * its revision date is a normal case — so an unparseable value is reported as
     * absent rather than raised.
     */
    private function displayDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * How the order already held differs from the one arriving.
     *
     * Without this the question is unanswerable: "is this a reissue?" cannot be decided
     * from a purchase-order number alone. The revision date, the ship quantity and the
     * line count are what let someone tell a genuine reissue from a stale re-upload.
     *
     * @param  Collection<int, PurchaseOrder>  $existing
     * @return array<string, mixed>|null
     */
    private function heldSummary(Collection $existing): ?array
    {
        if ($existing->isEmpty()) {
            return null;
        }

        $current = $existing->firstWhere('is_current', true)
            ?? $existing->sortByDesc('revision_no')->first();

        return [
            'revision_no' => (int) $current->revision_no,
            'revised_at' => $current->revised_at?->toDateTimeString(),
            'revised_by' => $current->revised_by,
            'total_qty' => $current->total_qty,
            'line_item_count' => $current->lineItems()->count(),
            'imported_at' => $current->created_at?->toDateTimeString(),
        ];
    }

    /**
     * The content-derived half of a `purchase_orders` row.
     *
     * `po_import_id`, `revision_no` and `is_current` are **deliberately absent**: they
     * depend on when and how the row is written, not on what the document says, and
     * staging a placeholder for them would be storing a guess. {@see self::writeOrder()}
     * supplies them.
     *
     * **Scalars and arrays only.** `po_type` is reduced to its backing value rather than
     * left as a {@see PoType}, so this array is byte-identical before and after a JSON
     * round trip through `po_imports.staged_orders`.
     *
     * @return array<string, mixed>
     */
    private function orderAttributes(
        PurchaseOrderDto $po,
        int $buyerId,
        string $poNumber,
        string $sourceHash,
        string $templateFingerprint,
    ): array {
        return [
            'buyer_id' => $buyerId,
            'po_number' => $poNumber,
            'revised_at' => $po->header?->revisedDate,
            'revised_by' => $po->header?->revisedBy,
            'source_hash' => $sourceHash,
            'document_status' => $po->header?->status,
            'quote_id' => $po->header?->quoteId,
            // `fromCode()` falls back to `Unknown` rather than null, so there is
            // nothing to guard against here.
            'po_type' => PoType::fromCode($po->logistics?->get('po_type'))->value,
            'create_date' => $po->header?->createDate,
            'negotiation_date' => $po->header?->negotiationDate,
            'vendor_ship_date' => $po->summary?->vendorShipDate,
            'cancel_date' => $po->summary?->cancelDate,
            'currency' => $po->header?->bidCurrency,
            'exchange_rate' => $po->header?->exchangeRate,
            'total_cartons' => $po->summary?->masterCartons,
            'total_qty' => $po->summary?->quantityEa,
            'total_weight_kgs' => $po->summary?->totalWeightKgs,
            'total_volume_cbm' => $po->summary?->totalVolumeCbm,
            'net_first_cost_usd' => $po->summary?->netFirstCost['usd'] ?? null,
            'net_first_cost_cnd' => $po->summary?->netFirstCost['cnd'] ?? null,
            'vendor_name' => $po->masterData?->vendorName,
            'factory_id' => $po->factory?->factoryId,
            'factory_name' => $po->factory?->name,
            'template_fingerprint' => $templateFingerprint,
            // Graded per order, not per file: one bad order in a document of three
            // must not mark the other two for review.
            'parse_status' => $this->grader->status($po->warnings),
            'confidence' => $this->grader->confidence($po->warnings),
            'payload' => $po->toArray(),
        ];
    }

    /**
     * Insert one staged order and its line items.
     *
     * The single write path. Phase one calls it for orders that collide with nothing;
     * {@see self::resolve()} calls it for the ones a human confirmed. Neither knows
     * anything the other does not.
     *
     * @param  StagedOrder  $staged
     */
    private function writeOrder(PoImport $import, array $staged, int $revisionNo): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            ...$staged['attributes'],
            'po_import_id' => $import->id,
            'revision_no' => $revisionNo,
            'is_current' => true,
        ]);

        $order->lineItems()->createMany($staged['line_items']);

        /*
         * Connect the new lines to the BQS rows that planned them. Done here rather
         * than in the controller because this is the single write path for a new
         * order — import, and the `revise` branch of a resolved conflict both reach
         * it — so a link is never missed by a caller that forgot.
         */
        $this->linker->linkForPurchaseOrder($order);

        return $order;
    }

    /**
     * Flatten every pack's line items into insertable rows.
     *
     * Pack identity is carried onto each line — see the `po_line_items` migration for
     * why there is no packs table.
     *
     * @return list<array<string, mixed>>
     */
    private function lineItemRows(PurchaseOrderDto $po): array
    {
        $rows = [];

        foreach ($po->packs as $pack) {
            foreach ($pack->lineItems as $item) {
                $rows[] = [
                    'pack_number' => $pack->packNumber,
                    'pack_description' => $pack->packDescription,
                    'assortment_id' => $pack->assortmentId,
                    'vendor_stock' => $pack->vendorStock,
                    'color' => $item->color,
                    'size' => $item->size,
                    'quantity' => $item->quantity,
                    /*
                     * Denormalised from the pack, because `quantity` above is the size
                     * ratio *inside* one pack and this is how many packs were ordered.
                     * The two multiply to the ordered quantity — see
                     * {@see PoLineItem::orderedUnits()}, which is the only thing that
                     * should ever do that arithmetic.
                     */
                    'total_cartons_per_line' => $pack->lineItemHeader?->get('total_cartons_per_line'),
                    'item_number' => $item->itemNumber,
                    'vendor_stock_number' => $item->vendorStockNumber,
                    'mfg_stock_number' => $item->mfgStockNumber,
                    'product_number' => $item->productNumber,
                    'upc_number' => $item->upcNumber,
                    'item_description1' => $item->itemDescription1,
                    'item_description2' => $item->itemDescription2,
                    'upc_description' => $item->upcDescription,
                    'signing_description' => $item->signingDescription,
                    'uom_qty' => $item->uomQty,
                    'uom_code' => $item->uomCode,
                ];
            }
        }

        return $rows;
    }

    /**
     * Every revision of this order already held, for the duplicate and revision checks.
     *
     * **Deliberately unscoped.** The unique indexes are enforced by the database
     * across all buyers, so an integrity check must see what they see; a scoped query
     * could miss a row and turn a clean "already imported" message into a constraint
     * violation. The buyer is already validated as one the actor may use
     * ({@see PurchaseOrderImportRequest}), so this
     * reveals nothing the actor could not otherwise reach.
     *
     * @return Collection<int, PurchaseOrder>
     */
    private function existingRevisions(int $buyerId, string $poNumber)
    {
        return PurchaseOrder::withoutBuyerScope()
            ->where('buyer_id', $buyerId)
            ->where('po_number', $poNumber)
            // Beyond the integrity check, these are what `heldSummary()` shows the
            // uploader so they can tell a reissue from a stale re-upload.
            ->get([
                'id', 'revision_no', 'source_hash', 'is_current',
                'revised_at', 'revised_by', 'total_qty', 'created_at',
            ]);
    }

    /**
     * A stable digest of one purchase order's parsed content.
     *
     * Hashing the parsed payload rather than the uploaded file is what makes this
     * per-order: a document holds several orders, and re-uploading it should be able
     * to refuse each one independently.
     *
     * The limitation worth knowing: a document re-exported to another format may parse
     * identically and hash identically, which is the intent — but a cosmetic change
     * Walmart makes without touching the revision date will hash differently and be
     * stored as a new revision.
     */
    private function sourceHash(PurchaseOrderDto $po): string
    {
        return hash('sha256', (string) json_encode($po->toArray()));
    }

    /**
     * The import this user left unanswered, if there is one.
     *
     * `PoImport` is buyer-scoped, and `inserted_by` narrows it further to the person
     * who uploaded it — you resume your own decisions, not a colleague's. Only the
     * latest is offered: a second upload while one is pending replaces it, and a
     * queue of stale conflicts is worse than none.
     *
     * @return array{id: int, source_file_name: string, imported_count: int, conflicts: list<array<string, mixed>>}|null
     */
    public function pendingFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $import = PoImport::query()
            ->pending()
            ->where('inserted_by', $user->id)
            ->latest('id')
            ->first();

        return $import === null ? null : $this->pendingPayload($import);
    }

    /**
     * One pending import, shaped for the conflict step of the import dialog.
     *
     * **`attributes` and `line_items` never leave the server.** They are the rows to be
     * written, they can run to hundreds of line items across fifty orders, and the
     * browser has no use for them — it sends back a decision per PO number, not data.
     *
     * @return array{id: int, source_file_name: string, imported_count: int, conflicts: list<array<string, mixed>>}
     */
    public function pendingPayload(PoImport $import): array
    {
        return [
            'id' => $import->id,
            'source_file_name' => $import->source_file_name,
            'imported_count' => $import->purchaseOrders()->count(),
            'conflicts' => array_map(
                fn (array $conflict): array => [
                    'po_number' => $conflict['po_number'],
                    'held' => $conflict['held'],
                    'incoming' => $conflict['incoming'],
                ],
                $import->staged_orders ?? [],
            ),
        ];
    }

    /**
     * Apply the uploader's decision to every order the import left staged.
     *
     * One transaction: a document holding four conflicts resolves as four or as none.
     * An order with no decision defaults to {@see PoConflictDecision::Skip}, which is
     * also what cancelling the dialog sends — the discard path and the all-skip path
     * are deliberately the same code, so there is no second way to abandon an import.
     *
     * The staging is cleared either way. Leaving it would offer the same questions
     * again after they were answered.
     *
     * @param  array<string, PoConflictDecision>  $decisions  keyed by PO number
     */
    public function resolve(PoImport $import, array $decisions): PoResolveResult
    {
        return DB::transaction(function () use ($import, $decisions): PoResolveResult {
            $revised = [];
            $overwritten = [];
            $skipped = [];

            foreach ($import->staged_orders ?? [] as $staged) {
                $poNumber = (string) $staged['po_number'];

                match ($decisions[$poNumber] ?? PoConflictDecision::Skip) {
                    PoConflictDecision::Revise => $revised[] = $this->applyRevision($import, $staged),
                    PoConflictDecision::Overwrite => $overwritten[] = $this->applyOverwrite($import, $staged),
                    PoConflictDecision::Skip => $skipped[] = $poNumber,
                };
            }

            $import->update(['staged_orders' => null]);

            return new PoResolveResult(
                import: $import,
                revisedPoNumbers: $revised,
                overwrittenPoNumbers: $overwritten,
                skippedPoNumbers: $skipped,
            );
        });
    }

    /**
     * Store the staged order as the next revision, keeping everything already held.
     *
     * `is_current` is maintained here rather than derived from `max(revision_no)`, so
     * the list does not pay for a window function on every request.
     *
     * @param  StagedOrder  $staged
     */
    private function applyRevision(PoImport $import, array $staged): string
    {
        $poNumber = (string) $staged['po_number'];
        $buyerId = (int) $staged['attributes']['buyer_id'];

        $existing = $this->existingRevisions($buyerId, $poNumber);

        PurchaseOrder::withoutBuyerScope()
            ->where('buyer_id', $buyerId)
            ->where('po_number', $poNumber)
            ->update(['is_current' => false]);

        $this->writeOrder($import, $staged, ((int) $existing->max('revision_no')) + 1);

        return $poNumber;
    }

    /**
     * Replace the current revision in place. **This destroys stored data.**
     *
     * Only the current revision is touched: earlier ones survive, and `revision_no`
     * does not move, so the count never lies about how many times Walmart reissued the
     * order. The line items are deleted and rebuilt rather than reconciled — they have
     * no identity of their own beyond the order that owns them.
     *
     * The cost, which cannot be avoided while still honouring the instruction: the
     * superseded `source_hash` is gone, so re-uploading the *original* document is no
     * longer recognised as already imported and presents as a fresh conflict.
     * `documentation/merchandising.md` §3.5 records it.
     *
     * @param  StagedOrder  $staged
     */
    private function applyOverwrite(PoImport $import, array $staged): string
    {
        $poNumber = (string) $staged['po_number'];
        $buyerId = (int) $staged['attributes']['buyer_id'];

        $current = PurchaseOrder::withoutBuyerScope()
            ->where('buyer_id', $buyerId)
            ->where('po_number', $poNumber)
            ->orderByDesc('is_current')
            ->orderByDesc('revision_no')
            ->first();

        /*
         * The held order was deleted between staging and confirming. There is nothing
         * left to overwrite, so this becomes what it would have been had the conflict
         * never existed — a first import — rather than an error about a row the user
         * is no longer able to see.
         */
        if ($current === null) {
            $this->writeOrder($import, $staged, revisionNo: 1);

            return $poNumber;
        }

        $current->lineItems()->delete();

        // `revision_no` is absent from the update on purpose; see the docblock.
        $current->update([
            ...$staged['attributes'],
            'po_import_id' => $import->id,
            'is_current' => true,
        ]);

        $current->lineItems()->createMany($staged['line_items']);

        /*
         * The old lines were deleted, taking their links with them, so the replacement
         * lines have to be linked afresh. A manual decision survives regardless: it is
         * stored as a `BqsColourLink` rule rather than on the line itself.
         */
        $this->linker->linkForPurchaseOrder($current->refresh());

        return $poNumber;
    }
}
