<?php

use App\Modules\Billing\Http\Controllers\Vendor\MoneyFlowController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — money flow of the current store (decision #72):
|   vendor.finance.purchases.*    purchases owed to the platform (VEN-062)
|   vendor.finance.settlements.*  settlement payments vendor → platform (VEN-063)
|   vendor.finance.payouts.*      payouts received platform → store (VEN-064)
*/

Route::middleware('auth:vendor')->prefix('finance')->name('finance.')->controller(MoneyFlowController::class)->group(function (): void {
    Route::middleware('permission:finance.purchases.view,vendor')->group(function (): void {
        Route::get('purchases', 'purchases')->name('purchases.index');
        Route::get('purchases/data', 'purchasesData')->name('purchases.data');
        Route::get('purchases/export', 'purchasesExport')->name('purchases.export');
    });

    Route::middleware('permission:finance.settlements.view,vendor')->group(function (): void {
        Route::get('settlements', 'settlements')->name('settlements.index');
        Route::get('settlements/data', 'settlementsData')->name('settlements.data');
        Route::get('settlements/{reference}', 'settlement')->where('reference', 'ST-[A-Za-z0-9-]+')->name('settlements.show');
    });

    Route::middleware('permission:finance.payouts.view,vendor')->group(function (): void {
        Route::get('payouts', 'payouts')->name('payouts.index');
        Route::get('payouts/data', 'payoutsData')->name('payouts.data');
    });
});
