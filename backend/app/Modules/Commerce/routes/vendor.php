<?php

use App\Modules\Commerce\Http\Controllers\Vendor\OrderController;
use App\Modules\Commerce\Http\Controllers\Vendor\PaymentGatewayController;
use App\Modules\Commerce\Http\Controllers\Vendor\SettlementController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.sales.orders.*                orders (VEN-020 / 021), {order} = order uuid
|   vendor.finance.settle.*              pay platform (VEN-021d), {payment} = payment log id
|   vendor.settings.payment-gateways.*   store gateways (VEN-078 / 078a, the sidebar name)
*/

Route::middleware('auth:vendor')->group(function (): void {
    Route::prefix('sales/orders')->name('sales.orders.')->controller(OrderController::class)->group(function (): void {
        Route::middleware('permission:orders.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{order}', 'show')->whereUuid('order')->name('show');
            Route::get('{order}/invoice', 'invoice')->whereUuid('order')->name('invoice');
        });
        Route::get('export', 'export')->middleware('permission:orders.export,vendor')->name('export');
        Route::middleware('permission:orders.manage,vendor')->group(function (): void {
            Route::get('{order}/cancel', 'cancelForm')->whereUuid('order')->name('cancel.form');
            Route::post('{order}/cancel', 'cancel')->whereUuid('order')->name('cancel');
            Route::get('{order}/shipping', 'shippingForm')->whereUuid('order')->name('shipping.form');
            Route::put('{order}/shipping', 'updateShipping')->whereUuid('order')->name('shipping.update');
        });
    });

    Route::prefix('finance/settle')->name('finance.settle.')->controller(SettlementController::class)->middleware('permission:orders.manage,vendor')->group(function (): void {
        Route::get('{order}', 'show')->whereUuid('order')->name('show');
        Route::post('{order}', 'pay')->whereUuid('order')->name('pay');
        Route::get('payments/{payment}/result', 'result')->whereNumber('payment')->name('result');
        Route::get('payments/{payment}/cancel', 'cancel')->whereNumber('payment')->name('cancel');
    });

    Route::prefix('settings/payment-gateways')->name('settings.payment-gateways.')->controller(PaymentGatewayController::class)->group(function (): void {
        Route::middleware('permission:settings.payment-gateways.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{code}', 'show')->whereAlpha('code')->name('show');
        });
        Route::middleware('permission:settings.payment-gateways.manage,vendor')->group(function (): void {
            Route::put('{code}', 'update')->whereAlpha('code')->name('update');
            Route::post('{code}/test', 'test')->whereAlpha('code')->name('test');
            Route::post('{code}/primary', 'primary')->whereAlpha('code')->name('primary');
        });
    });
});
