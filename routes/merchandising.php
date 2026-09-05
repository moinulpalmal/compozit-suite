<?php

use App\Http\Controllers\Merchandising\BqsController;
use App\Http\Controllers\Merchandising\BqsImportController;
use App\Http\Controllers\Merchandising\BqsLinkController;
use App\Http\Controllers\Merchandising\DocumentFileController;
use App\Http\Controllers\Merchandising\DocumentUploadController;
use App\Http\Controllers\Merchandising\PurchaseOrderController;
use App\Http\Controllers\Merchandising\PurchaseOrderImportController;
use App\Http\Controllers\Merchandising\TnaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchandising Module
|--------------------------------------------------------------------------
|
| Development tech packs, BQS & purchase orders, and fabric/accessory
| booking management. Every route here is name-prefixed with
| `merchandising.` and URL-prefixed with `merchandising`.
|
| See ARCHITECTURE.md → "Module 3 — Merchandising" and
| documentation/merchandising.md.
|
*/

Route::middleware(['auth', 'auth.session', 'verified'])
    ->prefix('merchandising')
    ->name('merchandising.')
    ->group(function (): void {

        /*
         * Purchase orders are imported from a buyer's document and never created by
         * hand, so `import` is its own permission rather than an alias for `create`:
         * running a parser over an upload is a different power from typing an order.
         *
         * **There is no GET for the form.** It is a modal on the list page, like every
         * other create surface in this application (ARCHITECTURE.md §5).
         *
         * The import routes are declared before `{purchaseOrder}` so that
         * `/purchase-orders/import` is not captured as an order id.
         */
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
            ->middleware('permission:merchandising.purchase-orders.view')
            ->name('purchase-orders.index');

        Route::post('purchase-orders/import', [PurchaseOrderImportController::class, 'store'])
            ->middleware('permission:merchandising.purchase-orders.import')
            ->name('purchase-orders.import.store');

        /*
         * A document holds up to fifty orders, so the ones that collide with an order
         * already held cannot be resolved inside the upload request — they are staged
         * and answered here. `overwrite` additionally needs
         * `merchandising.purchase-orders.delete`, enforced in the form request.
         */
        Route::post('purchase-orders/imports/{poImport}/resolve', [PurchaseOrderImportController::class, 'resolve'])
            ->middleware('permission:merchandising.purchase-orders.import')
            ->name('purchase-orders.import.resolve');

        /*
         * Which BQS row a purchase-order colour belongs to, when the documents did not
         * agree by themselves. Declared before `{purchaseOrder}` for the same reason
         * the import routes are.
         *
         * **One idempotent route, not a create and a delete**: a null `bqs_row_id`
         * clears the link, so linking and unlinking are one code path. Gated on
         * `update` rather than a new permission — it edits an order that already
         * exists, and nothing else edits one, so a separate ability would name a
         * distinction the application does not yet make.
         */
        Route::put('purchase-orders/{purchaseOrder}/bqs-link', [BqsLinkController::class, 'update'])
            ->middleware('permission:merchandising.purchase-orders.update')
            ->name('purchase-orders.bqs-link.update');

        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->middleware('permission:merchandising.purchase-orders.view')
            ->name('purchase-orders.show');

        /*
         * A BQS is the buyer's buy plan workbook, uploaded rather than typed — so
         * `import` is its own permission here too, for the same reason it is on
         * purchase orders: reading a workbook is a different power from entering a
         * record by hand.
         *
         * **There is no GET for the form.** It is a modal on the list page.
         *
         * As above, the import routes are declared before `{bqsSheet}` so that
         * `/bqs/import` is not captured as a sheet id.
         */
        Route::get('bqs', [BqsController::class, 'index'])
            ->middleware('permission:merchandising.bqs.view')
            ->name('bqs.index');

        Route::post('bqs/import', [BqsImportController::class, 'store'])
            ->middleware('permission:merchandising.bqs.import')
            ->name('bqs.import.store');

        /*
         * A workbook is one BQS, so this takes a single decision rather than the map
         * of them the purchase-order resolve route takes. `overwrite` additionally
         * needs `merchandising.bqs.delete`, enforced in the form request.
         */
        Route::post('bqs/imports/{bqsImport}/resolve', [BqsImportController::class, 'resolve'])
            ->middleware('permission:merchandising.bqs.import')
            ->name('bqs.import.resolve');

        Route::get('bqs/{bqsSheet}', [BqsController::class, 'show'])
            ->middleware('permission:merchandising.bqs.view')
            ->name('bqs.show');

        /*
         * The time-and-action board: every current order and when its milestones fall.
         *
         * **Read-only, and there is no write route to add.** The dates are computed
         * from a template in Settings and the ship date comes from the order, so a
         * correction is made where the data lives — `merchandising.tna.view` is the
         * whole permission story rather than the first of four.
         */
        Route::get('tna', [TnaController::class, 'index'])
            ->middleware('permission:merchandising.tna.view')
            ->name('tna.index');

        /*
         * The document library: files as they arrived, with nothing read out of them.
         *
         * **`create`, not `import`** — and the difference is the whole surface. The two
         * importers above run a parser over an upload, which is a distinct power worth
         * its own permission; nothing here parses, so `import` would name a
         * distinction the library does not make. A batch typed `bqs` is a stored
         * document and not an imported BQS (ARCHITECTURE.md §5, Module 3).
         *
         * **There is no GET for the form.** It is a modal on the list page, like every
         * other create surface in this application.
         */
        Route::get('documents', [DocumentUploadController::class, 'index'])
            ->middleware('permission:merchandising.documents.view')
            ->name('documents.index');

        Route::post('documents', [DocumentUploadController::class, 'store'])
            ->middleware('permission:merchandising.documents.create')
            ->name('documents.store');

        /*
         * **`scopeBindings()` is a security control here, not tidiness.**
         * `DocumentFile` carries no `buyer_id` and no global scope — it reaches its
         * buyer through its batch, which is the §9.2 rule for a child table — so a
         * file resolved on its own would be served to somebody the batch is hidden
         * from. Binding it through `$documentUpload->files()` puts the parent's scope
         * in front of it, and a mismatched pair 404s.
         *
         * Replace is `POST` rather than `PUT` because a browser form cannot send a
         * multipart body with `PUT`. It is gated on `update` at the route and
         * additionally requires `delete` in `DocumentFileReplaceRequest`: the file it
         * replaces is destroyed, and there is no version chain to recover it from.
         */
        Route::scopeBindings()->group(function (): void {
            Route::get('documents/{documentUpload}/files/{documentFile}/download', [DocumentFileController::class, 'download'])
                ->middleware('permission:merchandising.documents.view')
                ->name('documents.files.download');

            Route::get('documents/{documentUpload}/files/{documentFile}/preview', [DocumentFileController::class, 'preview'])
                ->middleware('permission:merchandising.documents.view')
                ->name('documents.files.preview');

            Route::post('documents/{documentUpload}/files/{documentFile}', [DocumentFileController::class, 'update'])
                ->middleware('permission:merchandising.documents.update')
                ->name('documents.files.update');

            Route::delete('documents/{documentUpload}/files/{documentFile}', [DocumentFileController::class, 'destroy'])
                ->middleware('permission:merchandising.documents.delete')
                ->name('documents.files.destroy');
        });

        /*
         * Declared after the nested file routes so `/documents/{id}` cannot capture a
         * path that was meant for one of them.
         */
        Route::get('documents/{documentUpload}', [DocumentUploadController::class, 'show'])
            ->middleware('permission:merchandising.documents.view')
            ->name('documents.show');

        Route::delete('documents/{documentUpload}', [DocumentUploadController::class, 'destroy'])
            ->middleware('permission:merchandising.documents.delete')
            ->name('documents.destroy');
    });
