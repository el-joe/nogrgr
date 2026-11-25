<?php

use App\Modules\Commerce\Http\Controllers\Vendor\CouponController;
use App\Modules\Commerce\Http\Controllers\Vendor\FlashSaleController;
use App\Modules\Commerce\Http\Controllers\Vendor\OrderController;
use App\Modules\Commerce\Http\Controllers\Vendor\PaymentGatewayController;
use App\Modules\Commerce\Http\Controllers\Vendor\SettlementController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.sales.orders.*                orders (VEN-020 / 021), {order} = order uuid
|   vendor.finance.settle.*              pay platform (VEN-021d), {payment} = payment log id
|   vendor.settings.payment-gateways.*   store gateways (VEN-078 / 078a, the sidebar name)
|   vendor.marketing.coupons.*           coupons (VEN-040 / 040a), {coupon} = store coupon id
|                                        (visibility: central coupon id)
|   vendor.marketing.flash-sales.*       flash sales (VEN-041 / 041a / 041b), {flashSale} =
|                                        store sale id (visibility: central id), {product} =
|                                        store product id
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

    Route::prefix('marketing/coupons')->name('marketing.coupons.')->controller(CouponController::class)->group(function (): void {
        Route::middleware('permission:coupons.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{coupon}', 'show')->whereNumber('coupon')->name('show');
        });
        Route::middleware('permission:coupons.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{coupon}/edit', 'edit')->whereNumber('coupon')->name('edit');
            Route::put('{coupon}', 'update')->whereNumber('coupon')->name('update');
            Route::delete('{coupon}', 'destroy')->whereNumber('coupon')->name('destroy');
            Route::post('central/{coupon}/visibility', 'visibility')->whereNumber('coupon')->name('visibility');
        });
    });

    Route::prefix('marketing/flash-sales')->name('marketing.flash-sales.')->controller(FlashSaleController::class)->group(function (): void {
        Route::middleware('permission:flash-sales.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{flashSale}', 'show')->whereNumber('flashSale')->name('show');
        });
        Route::middleware('permission:flash-sales.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('products/search', 'products')->name('products.search');
            Route::get('for-product/{product}', 'availableForProduct')->whereNumber('product')->name('available-for-product');
            Route::post('for-product/{product}', 'attachProduct')->whereNumber('product')->name('attach-product');
            Route::get('{flashSale}/edit', 'edit')->whereNumber('flashSale')->name('edit');
            Route::put('{flashSale}', 'update')->whereNumber('flashSale')->name('update');
            Route::delete('{flashSale}', 'destroy')->whereNumber('flashSale')->name('destroy');
            Route::post('central/{flashSale}/visibility', 'visibility')->whereNumber('flashSale')->name('visibility');
        });
    });
});
