<?php

use App\Http\Controllers\Merchandising\PurchaseOrderController;
use App\Http\Controllers\Merchandising\PurchaseOrderImportController;
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
         * The two import routes are declared before `{purchaseOrder}` so that
         * `/purchase-orders/import` is not captured as an order id.
         */
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
            ->middleware('permission:merchandising.purchase-orders.view')
            ->name('purchase-orders.index');

        Route::get('purchase-orders/import', [PurchaseOrderImportController::class, 'create'])
            ->middleware('permission:merchandising.purchase-orders.import')
            ->name('purchase-orders.import.create');

        Route::post('purchase-orders/import', [PurchaseOrderImportController::class, 'store'])
            ->middleware('permission:merchandising.purchase-orders.import')
            ->name('purchase-orders.import.store');

        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->middleware('permission:merchandising.purchase-orders.view')
            ->name('purchase-orders.show');
    });
