<?php

use App\Modules\AfterSales\Http\Controllers\Admin\OrderAfterSalesController;
use App\Modules\AfterSales\Http\Controllers\Admin\RefundController;
use App\Modules\AfterSales\Http\Controllers\Admin\ReturnController;
use App\Modules\AfterSales\Http\Controllers\Admin\ReturnPolicyController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — after-sales (decision #71), on the existing sidebar names:
|   admin.orders.refunds.* / admin.orders.returns.*  modals of the order detail (ADM-021b / 021c)
|   admin.returns.*                                  return requests (ADM-023 / 023a)
|   admin.refunds.*                                  refunds (ADM-024)
|   admin.storefront.return-policy.*                 platform return policy (ADM-090)
*/

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('orders/{fact}')->whereNumber('fact')->name('orders.')->controller(OrderAfterSalesController::class)->group(function (): void {
        Route::middleware('permission:refunds.manage,admin')->group(function (): void {
            Route::get('refund', 'refundForm')->name('refunds.create');
            Route::post('refund', 'refund')->name('refunds.store');
        });
        Route::middleware('permission:returns.manage,admin')->group(function (): void {
            Route::get('return', 'returnForm')->name('returns.create');
            Route::post('return', 'storeReturn')->name('returns.store');
        });
    });

    Route::prefix('returns')->name('returns.')->controller(ReturnController::class)->group(function (): void {
        Route::middleware('permission:returns.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{return}', 'show')->whereNumber('return')->name('show');
            Route::get('{return}/media/{media}', 'media')->whereNumber(['return', 'media'])->name('media');
        });
        Route::middleware('permission:returns.manage,admin')->group(function (): void {
            Route::get('{return}/actions/{action}', 'actionForm')->whereNumber('return')->name('actions.form');
            Route::post('{return}/actions/{action}', 'act')->whereNumber('return')->name('actions.store');
            Route::post('{return}/notes', 'note')->whereNumber('return')->name('notes.store');
        });
    });

    Route::prefix('refunds')->name('refunds.')->controller(RefundController::class)->group(function (): void {
        Route::middleware('permission:refunds.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('export', 'export')->name('export');
            Route::get('{refund}', 'show')->whereNumber('refund')->name('show');
        });
        Route::middleware('permission:refunds.manage,admin')->group(function (): void {
            Route::get('{refund}/{action}', 'actionForm')->whereNumber('refund')->whereIn('action', ['retry', 'complete', 'reject'])->name('action.form');
            Route::post('{refund}/action', 'act')->whereNumber('refund')->name('action');
        });
    });

    Route::prefix('storefront/return-policy')->name('storefront.return-policy.')->controller(ReturnPolicyController::class)->group(function (): void {
        Route::get('/', 'edit')->middleware('permission:storefront.return-policy.manage,admin')->name('edit');
        Route::middleware('permission:storefront.return-policy.manage,admin')->group(function (): void {
            Route::put('/', 'update')->name('update');
            Route::get('reasons/create', 'createReason')->name('reasons.create');
            Route::post('reasons', 'storeReason')->name('reasons.store');
            Route::get('reasons/{reason}/edit', 'editReason')->whereNumber('reason')->name('reasons.edit');
            Route::put('reasons/{reason}', 'updateReason')->whereNumber('reason')->name('reasons.update');
            Route::patch('reasons/{reason}/active', 'toggleReason')->whereNumber('reason')->name('reasons.toggle');
            Route::delete('reasons/{reason}', 'destroyReason')->whereNumber('reason')->name('reasons.destroy');
        });
    });
});
