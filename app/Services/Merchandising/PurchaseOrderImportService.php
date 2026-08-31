<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\Po\ParseResultDto;
use App\DataTransferObjects\Merchandising\Po\PurchaseOrderDto;
use App\DataTransferObjects\Merchandising\PoImportResult;
use App\Enums\Merchandising\PoType;
use App\Exceptions\Merchandising\PoParser\PoParserException;
use App\Exceptions\Merchandising\PoParser\TextExtractionException;
use App\Http\Requests\Merchandising\PurchaseOrderImportRequest;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\PoImport;
use App\Models\Merchandising\PurchaseOrder;
use App\Services\Merchandising\PoParser\ParserService;
use App\Services\Merchandising\PoParser\Support\ParseGrader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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
 * ## Duplicates and revisions
 *
 * Walmart reissues orders, and each document states its own revision
 * (`Revised Date … By:`). A purchase order already held is matched two ways:
 *
 * - the same `source_hash` — a byte-identical re-upload — which is **skipped**;
 * - the same PO number with different content, which becomes a **new revision** and
 *   takes `is_current` from the one before it.
 *
 * **A document whose orders are partly duplicates imports the rest.** Refusing all
 * three because the second was already held would lose two good orders to protect
 * nothing; the controller reports both counts, and {@see PoImportResult} carries them.
 */
final class PurchaseOrderImportService
{
    public function __construct(
        private readonly ParserService $parser,
        private readonly ParseGrader $grader,
    ) {}

    /**
     * The buyers this user may import for: active, and within their own access.
     *
     * Unpaginated by design — a picker and a list are different queries
     * (ARCHITECTURE.md §8.6), and a user's buyer set is short by construction.
     * `Buyer` is not itself buyer-scoped, so the access filter is applied here.
     *
     * @return array<int, string> buyer id => name
     */
    public function assignableBuyers(): array
    {
        $query = Buyer::query()->active();

        $actor = Auth::user();

        if ($actor !== null && ! $actor->seesAllBuyers()) {
            $query->whereIn('id', $actor->accessibleBuyerIds());
        }

        /** @var array<int, string> $buyers */
        $buyers = $query->orderBy('name')->pluck('name', 'id')->all();

        return $buyers;
    }

    /**
     * The same set, shaped for `components/ui/combobox.tsx`.
     *
     * @return list<array{value: int, label: string}>
     */
    public function assignableBuyerOptions(): array
    {
        $options = [];

        foreach ($this->assignableBuyers() as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name];
        }

        return $options;
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
        $revised = [];
        $duplicates = [];

        foreach ($result->purchaseOrders as $po) {
            $poNumber = (string) $po->header?->poNumber;
            $sourceHash = $this->sourceHash($po);

            $existing = $this->existingRevisions($buyerId, $poNumber);

            if ($existing->contains('source_hash', $sourceHash)) {
                $duplicates[] = $poNumber;

                continue;
            }

            $revisionNo = ((int) $existing->max('revision_no')) + 1;

            if ($revisionNo > 1) {
                // Only the newest revision is current, and the flag is maintained
                // here rather than derived, so the list does not pay for a window
                // function on every request.
                PurchaseOrder::withoutBuyerScope()
                    ->where('buyer_id', $buyerId)
                    ->where('po_number', $poNumber)
                    ->update(['is_current' => false]);

                $revised[] = $poNumber;
            } else {
                $imported[] = $poNumber;
            }

            $this->storePurchaseOrder($import, $po, $buyerId, $poNumber, $sourceHash, $revisionNo);
        }

        return new PoImportResult(
            import: $import,
            importedPoNumbers: $imported,
            revisedPoNumbers: $revised,
            duplicatePoNumbers: $duplicates,
        );
    }

    private function storePurchaseOrder(
        PoImport $import,
        PurchaseOrderDto $po,
        int $buyerId,
        string $poNumber,
        string $sourceHash,
        int $revisionNo,
    ): void {
        $order = PurchaseOrder::create([
            'po_import_id' => $import->id,
            'buyer_id' => $buyerId,
            'po_number' => $poNumber,
            'revision_no' => $revisionNo,
            'revised_at' => $po->header?->revisedDate,
            'revised_by' => $po->header?->revisedBy,
            'source_hash' => $sourceHash,
            'is_current' => true,
            'document_status' => $po->header?->status,
            'quote_id' => $po->header?->quoteId,
            'po_type' => PoType::fromCode($po->logistics?->get('po_type')),
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
            'template_fingerprint' => $import->template_fingerprint,
            // Graded per order, not per file: one bad order in a document of three
            // must not mark the other two for review.
            'parse_status' => $this->grader->status($po->warnings),
            'confidence' => $this->grader->confidence($po->warnings),
            'payload' => $po->toArray(),
        ]);

        $order->lineItems()->createMany($this->lineItemRows($po));
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
            ->get(['id', 'revision_no', 'source_hash']);
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
}
