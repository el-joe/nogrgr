<?php

use App\Modules\AfterSales\Http\Controllers\Vendor\VendorAfterSalesController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — after-sales of the current store:
|   vendor.sales.orders.refund.*   manual refund modal of an order (VEN-021b)
|   vendor.sales.returns.*         returns (VEN-022 / 022a)
|   vendor.sales.refunds.*         refunds (VEN-023)
*/

Route::middleware('auth:vendor')->prefix('sales')->name('sales.')->controller(VendorAfterSalesController::class)->group(function (): void {
    Route::middleware('permission:refunds.manage,vendor')->group(function (): void {
        Route::get('orders/{order}/refund', 'refundForm')->whereUuid('order')->name('orders.refund.create');
        Route::post('orders/{order}/refund', 'refund')->whereUuid('order')->name('orders.refund.store');
    });

    Route::middleware('permission:returns.view,vendor')->group(function (): void {
        Route::get('returns', 'returns')->name('returns.index');
        Route::get('returns/data', 'returnsData')->name('returns.data');
        Route::get('returns/{return}', 'showReturn')->whereNumber('return')->name('returns.show');
        Route::get('returns/{return}/media/{media}', 'returnMedia')->whereNumber(['return', 'media'])->name('returns.media');
    });
    Route::middleware('permission:returns.manage,vendor')->group(function (): void {
        Route::get('returns/{return}/actions/{action}', 'returnActionForm')->whereNumber('return')->name('returns.actions.form');
        Route::post('returns/{return}/actions/{action}', 'returnAct')->whereNumber('return')->name('returns.actions.store');
        Route::post('returns/{return}/notes', 'returnNote')->whereNumber('return')->name('returns.notes.store');
    });

    Route::middleware('permission:refunds.view,vendor')->group(function (): void {
        Route::get('refunds', 'refunds')->name('refunds.index');
        Route::get('refunds/data', 'refundsData')->name('refunds.data');
        Route::get('refunds/{refund}', 'showRefund')->whereNumber('refund')->name('refunds.show');
    });
    Route::middleware('permission:refunds.manage,vendor')->group(function (): void {
        Route::get('refunds/{refund}/{action}', 'refundActionForm')->whereNumber('refund')->whereIn('action', ['retry', 'complete', 'reject'])->name('refunds.action.form');
        Route::post('refunds/{refund}/action', 'refundAct')->whereNumber('refund')->name('refunds.action');
    });
});
