<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\BqsImportResult;
use App\DataTransferObjects\Merchandising\BqsResolveResult;
use App\DataTransferObjects\Merchandising\BqsRowDto;
use App\DataTransferObjects\Merchandising\BqsWorkbookDto;
use App\Enums\Merchandising\BqsConflictDecision;
use App\Enums\Merchandising\BqsFileType;
use App\Exceptions\Merchandising\BqsImportException;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsSheet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Imports a BQS workbook, and answers the one question an upload can raise.
 *
 * ## The order of operations, and why
 *
 * 1. **Store the file, then read it, both outside any transaction.** PhpSpreadsheet
 *    loads a workbook into memory; holding a database transaction open across that
 *    pins a connection for no benefit.
 * 2. **Write everything inside one transaction.** A BQS is a sheet, its rows, and each
 *    row's months and pack sizes — hundreds of inserts that are meaningless
 *    individually. It lands whole or not at all.
 *
 * ## Idempotency and collisions are different questions
 *
 * `unique(buyer_id, source_hash)` on `bqs_imports` answers the first: the **same
 * bytes** twice is nothing new, so it is silently skipped rather than reported as an
 * error. Nothing changed, so there is nothing to ask about.
 *
 * The second is harder, because a BQS workbook has no document number, no revision
 * date and a blank `Quote ID`. {@see BqsRowKey} explains the answer in full: two
 * uploads are the same BQS when their row-key sets intersect. That is detected here by
 * {@see self::collidingSheet()} and answered **once for the whole workbook**, not once
 * per row — a 200-row BQS would otherwise produce a 200-decision dialog.
 *
 * @phpstan-type StagedRows array{
 *     collides_with_sheet_id: int,
 *     collides_with_title: string,
 *     collides_with_revision: int,
 *     overlapping_rows: int,
 *     workbook: array<string, mixed>,
 *     rows: list<array<string, mixed>>
 * }
 */
class BqsImportService
{
    public function __construct(
        protected BqsWorkbookReader $reader,
        protected BqsPoLinker $linker,
    ) {}

    /**
     * Read an uploaded workbook and store the BQS it holds.
     *
     * @throws BqsImportException when the workbook is not a readable BQS
     */
    public function import(UploadedFile $file, int $buyerId, string $bqsDate): BqsImportResult
    {
        $type = BqsFileType::detect($file->getRealPath());

        if (! $type instanceof BqsFileType) {
            throw BqsImportException::unsupportedFormat($file->getClientOriginalName());
        }

        $sourceHash = (string) hash_file('sha256', $file->getRealPath());
        $held = BqsImport::query()->where('buyer_id', $buyerId)->where('source_hash', $sourceHash)->first();

        /*
         * The same bytes, already held. Nothing changed, so nothing is asked and
         * nothing is written — the import that holds them is handed back so the
         * controller can point at it.
         */
        if ($held instanceof BqsImport) {
            return new BqsImportResult($held, null, isDuplicate: true, isStaged: false);
        }

        $storedPath = $this->storeFile($file);
        $absolutePath = $storedPath === null
            ? $file->getRealPath()
            : Storage::disk($this->disk())->path($storedPath);

        try {
            $workbook = $this->reader->read(
                $absolutePath,
                $file->getClientOriginalName(),
                (int) config('bqs-import.limits.max_rows'),
            );

            $this->refuseDuplicateRows($workbook);
        } catch (BqsImportException $exception) {
            $this->discard($storedPath);

            throw $exception;
        }

        return DB::transaction(
            fn (): BqsImportResult => $this->store($workbook, $file, $type, $buyerId, $bqsDate, $sourceHash, $storedPath)
        );
    }

    /**
     * Apply the uploader's decision to a BQS this import held back.
     *
     * One transaction: the decision lands whole or not at all, and `staged_rows` is
     * cleared in the same breath so an answered import can never be answered twice.
     */
    public function resolve(BqsImport $import, BqsConflictDecision $decision): BqsResolveResult
    {
        return DB::transaction(function () use ($import, $decision): BqsResolveResult {
            /** @var StagedRows|null $staged */
            $staged = $import->staged_rows;

            if ($staged === null) {
                return new BqsResolveResult($decision, null);
            }

            $held = BqsSheet::query()->find($staged['collides_with_sheet_id']);
            $title = $staged['collides_with_title'];

            $sheet = match ($decision) {
                BqsConflictDecision::Skip => null,
                BqsConflictDecision::Revise => $this->writeRevision($import, $staged, $held),
                BqsConflictDecision::Overwrite => $this->overwrite($import, $staged, $held),
            };

            $import->forceFill(['staged_rows' => null])->save();

            return new BqsResolveResult($decision, $sheet, $title);
        });
    }

    /**
     * The uploader's own import still waiting on a decision, if there is one.
     *
     * **Only the uploader's**, so the dialog reopens for the person who chose the file
     * and for nobody else. A colleague deciding "reissue or stale?" about a workbook
     * they have not seen is not a decision anyone should be asked to make; their honest
     * fallback is to upload it themselves.
     *
     * @return array<string, mixed>|null
     */
    public function pendingFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $import = BqsImport::query()
            ->pending()
            ->where('inserted_by', $user->id)
            ->latest('id')
            ->first();

        if (! $import instanceof BqsImport) {
            return null;
        }

        /** @var StagedRows $staged */
        $staged = $import->staged_rows;

        return [
            'id' => $import->id,
            'source_file_name' => $import->source_file_name,
            'bqs_date' => $import->bqs_date->toDateString(),
            'row_count' => $import->row_count,
            'collides_with_title' => $staged['collides_with_title'],
            'collides_with_revision' => $staged['collides_with_revision'],
            'overlapping_rows' => $staged['overlapping_rows'],
            'warnings' => $staged['workbook']['warnings'],
        ];
    }

    /**
     * Write the import row, then either the BQS or the question about it.
     */
    private function store(
        BqsWorkbookDto $workbook,
        UploadedFile $file,
        BqsFileType $type,
        int $buyerId,
        string $bqsDate,
        string $sourceHash,
        ?string $storedPath,
    ): BqsImportResult {
        $import = BqsImport::query()->create([
            'buyer_id' => $buyerId,
            'bqs_date' => $bqsDate,
            'source_file_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'detected_file_type' => $type,
            'sheet_name' => $workbook->sheetName,
            'header_fingerprint' => $workbook->headerFingerprint,
            'row_count' => count($workbook->rows),
            'parse_status' => $workbook->status(),
            'source_hash' => $sourceHash,
            'payload' => $workbook->toArray(),
        ]);

        $collision = $this->collidingSheet($workbook, $buyerId);

        if ($collision instanceof BqsSheet) {
            $import->forceFill(['staged_rows' => $this->stage($workbook, $collision)])->save();

            return new BqsImportResult(
                $import,
                sheet: null,
                isDuplicate: false,
                isStaged: true,
                collidesWith: $collision->title,
            );
        }

        $sheet = $this->writeSheet($import, $workbook, revisionNo: 1, rootId: null);

        return new BqsImportResult($import, $sheet, isDuplicate: false, isStaged: false);
    }

    /**
     * The current BQS this workbook's rows overlap, if there is exactly one.
     *
     * **Two or more is refused rather than guessed at.** A workbook straddling two
     * held revisions is a revision of neither, and silently picking one would orphan
     * the other with no trace that it happened.
     *
     * `withoutBuyerScope()` is not used and must not be: the collision has to be
     * judged against what *this user* can see, or an upload would be blocked by a BQS
     * they have no access to and cannot resolve.
     *
     * @throws BqsImportException
     */
    private function collidingSheet(BqsWorkbookDto $workbook, int $buyerId): ?BqsSheet
    {
        $sheetIds = BqsRow::query()
            ->whereIn('row_key', $workbook->rowKeys())
            ->whereIn('bqs_sheet_id', BqsSheet::query()->current()->where('buyer_id', $buyerId)->select('id'))
            ->distinct()
            ->pluck('bqs_sheet_id');

        if ($sheetIds->isEmpty()) {
            return null;
        }

        $sheets = BqsSheet::query()->whereIn('id', $sheetIds)->get();

        if ($sheets->count() > 1) {
            throw BqsImportException::straddlesRevisions(
                $sheets->map(fn (BqsSheet $sheet): string => "{$sheet->title} (rev {$sheet->revision_no})")->all()
            );
        }

        return $sheets->first();
    }

    /**
     * Hold the whole workbook until the uploader answers.
     *
     * The rows are staged as arrays rather than re-read on resolve: the uploaded file
     * may not have been retained, and re-reading it would in any case be a second
     * chance for the two passes to disagree.
     *
     * @return StagedRows
     */
    private function stage(BqsWorkbookDto $workbook, BqsSheet $collision): array
    {
        $keys = BqsRow::query()
            ->where('bqs_sheet_id', $collision->id)
            ->whereIn('row_key', $workbook->rowKeys())
            ->count();

        return [
            'collides_with_sheet_id' => $collision->id,
            'collides_with_title' => $collision->title,
            'collides_with_revision' => $collision->revision_no,
            'overlapping_rows' => $keys,
            'workbook' => [
                'sheet_name' => $workbook->sheetName,
                'header_fingerprint' => $workbook->headerFingerprint,
                'unmapped_columns' => $workbook->unmappedColumns,
                'warnings' => $workbook->warnings,
                'header' => $workbook->sheetHeader(),
            ],
            'rows' => array_map(
                static fn (BqsRowDto $row): array => [
                    'values' => $row->toArray(),
                    'months' => $row->months,
                    'pack_sizes' => array_map(
                        static fn (array $pack): array => [...$pack, 'pack_type' => $pack['pack_type']->value],
                        $row->packSizes,
                    ),
                ],
                $workbook->rows,
            ),
        ];
    }

    /**
     * Store the staged workbook as the next revision, keeping what is held.
     *
     * @param  StagedRows  $staged
     */
    private function writeRevision(BqsImport $import, array $staged, ?BqsSheet $held): ?BqsSheet
    {
        if (! $held instanceof BqsSheet) {
            return null;
        }

        $rootId = $held->root_id ?? $held->id;

        $next = (int) BqsSheet::query()->where('root_id', $rootId)->max('revision_no') + 1;

        BqsSheet::query()->where('root_id', $rootId)->update(['is_current' => false]);

        $sheet = $this->writeStaged($import, $staged, revisionNo: $next, rootId: $rootId);

        /*
         * Move every purchase-order link from the superseded revision's rows to the
         * new ones, matched on `row_key`. Without this a reissue silently orphans
         * every link — including the manual ones — and the only symptom is a BQS
         * reporting nothing ordered.
         */
        $this->linker->carryForward($held, $sheet);

        return $sheet;
    }

    /**
     * Replace the current revision in place, destroying its rows.
     *
     * The cascade on `bqs_rows.bqs_sheet_id` takes the rows, and theirs takes the
     * months and pack sizes — which is why this is gated on `merchandising.bqs.delete`
     * rather than `import`.
     *
     * @param  StagedRows  $staged
     */
    private function overwrite(BqsImport $import, array $staged, ?BqsSheet $held): ?BqsSheet
    {
        if (! $held instanceof BqsSheet) {
            return null;
        }

        $revisionNo = $held->revision_no;
        $rootId = $held->root_id;

        /*
         * **Captured before the delete, and it has to be.** `po_line_items.bqs_row_id`
         * is `nullOnDelete`, so the cascade below erases every purchase-order link the
         * moment the held sheet goes. Restoring afterwards from what was captured here
         * is the only order that survives it — and getting it backwards fails silently,
         * leaving a BQS that reports nothing ordered.
         */
        $links = $this->linker->captureLinks($held);

        /*
         * When the sheet being replaced is its own root, deleting it would cascade
         * through `root_id` and take every later revision with it. Detaching the
         * children first is what keeps an overwrite of revision 1 from destroying
         * revisions 2 and 3 — which cannot happen today, because only the *current*
         * revision is ever overwritten and the root is current only when it is alone,
         * but the guard costs one statement and the cascade is not obvious.
         */
        if ($rootId === $held->id) {
            BqsSheet::query()->where('root_id', $held->id)->whereKeyNot($held->id)->update(['root_id' => null]);
            $rootId = null;
        }

        $held->delete();

        $sheet = $this->writeStaged($import, $staged, revisionNo: $revisionNo, rootId: $rootId);

        if ($rootId === null) {
            BqsSheet::query()->whereNull('root_id')->whereKeyNot($sheet->id)->update(['root_id' => $sheet->id]);
        }

        $this->linker->restoreLinks($links, $sheet);

        return $sheet;
    }

    /**
     * Write a sheet and its rows from a freshly read workbook.
     */
    private function writeSheet(BqsImport $import, BqsWorkbookDto $workbook, int $revisionNo, ?int $rootId): BqsSheet
    {
        $sheet = $this->createSheet($import, [
            ...$workbook->sheetHeader(),
            'revision_no' => $revisionNo,
            'row_count' => count($workbook->rows),
            'payload' => ['warnings' => $workbook->warnings],
        ], $rootId);

        foreach ($workbook->rows as $row) {
            $this->writeRow($sheet, $row->toArray(), $row->months, array_map(
                static fn (array $pack): array => [...$pack, 'pack_type' => $pack['pack_type']->value],
                $row->packSizes,
            ));
        }

        /*
         * Purchase orders are routinely imported before the BQS that planned them, so
         * a new sheet claims whatever is already on file and unlinked. The mirror of
         * the call `PurchaseOrderImportService::writeOrder()` makes; without both, half
         * the links never form and the failure is invisible.
         */
        $this->linker->linkForSheet($sheet);

        return $sheet;
    }

    /**
     * Write a sheet and its rows from a staged payload.
     *
     * @param  StagedRows  $staged
     */
    private function writeStaged(BqsImport $import, array $staged, int $revisionNo, ?int $rootId): BqsSheet
    {
        $sheet = $this->createSheet($import, [
            ...$staged['workbook']['header'],
            'revision_no' => $revisionNo,
            'row_count' => count($staged['rows']),
            'payload' => ['warnings' => $staged['workbook']['warnings']],
        ], $rootId);

        foreach ($staged['rows'] as $row) {
            $this->writeRow($sheet, $row['values'], $row['months'], $row['pack_sizes']);
        }

        $this->linker->linkForSheet($sheet);

        return $sheet;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSheet(BqsImport $import, array $attributes, ?int $rootId): BqsSheet
    {
        $sheet = BqsSheet::query()->create([
            'bqs_import_id' => $import->id,
            'buyer_id' => $import->buyer_id,
            'root_id' => $rootId,
            'bqs_date' => $import->bqs_date,
            'title' => $import->source_file_name,
            'is_current' => true,
            'source_hash' => $import->source_hash,
            'parse_status' => $import->parse_status,
            ...$attributes,
        ]);

        /*
         * Revision 1 is its own root, and the id does not exist until the insert
         * returns — see the migration docblock for why this is not a nullable column
         * with NULL meaning "root".
         */
        if ($rootId === null) {
            $sheet->forceFill(['root_id' => $sheet->id])->save();
        }

        return $sheet;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array{month: string, month_label: string, dc_units: int|null}>  $months
     * @param  list<array{pack_type: string, size_label: string, size_order: int, quantity: int|null}>  $packSizes
     */
    private function writeRow(BqsSheet $sheet, array $values, array $months, array $packSizes): void
    {
        /** @var BqsRow $row */
        $row = $sheet->rows()->create($values);

        if ($months !== []) {
            $row->months()->createMany($months);
        }

        if ($packSizes !== []) {
            $row->packSizes()->createMany($packSizes);
        }
    }

    /**
     * Refuse a workbook describing the same style and colour twice.
     *
     * The owner's rule is one row per style + colour, so a duplicate means two rows
     * disagree about a quantity with no way to tell which is meant. Storing both would
     * also break the row-key intersection the revision mechanism rests on.
     *
     * @throws BqsImportException
     */
    private function refuseDuplicateRows(BqsWorkbookDto $workbook): void
    {
        $seen = [];

        foreach ($workbook->rows as $row) {
            $key = $row->key();

            if (isset($seen[$key])) {
                throw BqsImportException::duplicateRow($seen[$key], $row->lineNo, $row->describe());
            }

            $seen[$key] = $row->lineNo;
        }
    }

    /**
     * Keep the uploaded workbook, unless configuration says not to.
     */
    private function storeFile(UploadedFile $file): ?string
    {
        if (! config('bqs-import.storage.retain_original')) {
            return null;
        }

        $path = $file->store((string) config('bqs-import.storage.upload'), $this->disk());

        return $path === false ? null : $path;
    }

    private function discard(?string $storedPath): void
    {
        if ($storedPath !== null) {
            Storage::disk($this->disk())->delete($storedPath);
        }
    }

    private function disk(): string
    {
        return (string) config('bqs-import.storage.disk');
    }
}
