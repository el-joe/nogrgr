<?php

use App\Modules\Commerce\Http\Controllers\Admin\CouponController;
use App\Modules\Commerce\Http\Controllers\Admin\FlashSaleController;
use App\Modules\Commerce\Http\Controllers\Admin\OrderController;
use App\Modules\Commerce\Http\Controllers\Admin\ShippingQueueController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/promotions/*, names "admin.promotions.*" (ADM-025, 026);
| orders "admin.orders.*" (ADM-020 / 021) and the shipping queue "admin.shipping-queue.*"
| (ADM-022 / 022a) — the existing sidebar names (decision #69). {fact} = order_facts.id.
*/

Route::middleware('auth:admin')->prefix('promotions')->name('promotions.')->group(function (): void {
    // ADM-025 / 025a Central coupons
    Route::prefix('coupons')->name('coupons.')->controller(CouponController::class)->group(function (): void {
        Route::middleware('permission:coupons.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });
        Route::middleware('permission:coupons.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{coupon}/edit', 'edit')->whereNumber('coupon')->name('edit');
            Route::put('{coupon}', 'update')->whereNumber('coupon')->name('update');
            Route::patch('{coupon}/active', 'toggle')->whereNumber('coupon')->name('toggle');
            Route::delete('{coupon}', 'destroy')->whereNumber('coupon')->name('destroy');
        });
        Route::get('{coupon}', 'show')->whereNumber('coupon')->middleware('permission:coupons.view,admin')->name('show');
    });

    // ADM-026 / 026a Central flash sales
    Route::prefix('flash-sales')->name('flash-sales.')->controller(FlashSaleController::class)->group(function (): void {
        Route::middleware('permission:flash-sales.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });
        Route::middleware('permission:flash-sales.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('products', 'products')->name('products');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{flashSale}/edit', 'edit')->whereNumber('flashSale')->name('edit');
            Route::put('{flashSale}', 'update')->whereNumber('flashSale')->name('update');
            Route::patch('{flashSale}/active', 'toggle')->whereNumber('flashSale')->name('toggle');
            Route::delete('{flashSale}', 'destroy')->whereNumber('flashSale')->name('destroy');
        });
        Route::get('{flashSale}', 'show')->whereNumber('flashSale')->middleware('permission:flash-sales.view,admin')->name('show');
    });
});

Route::middleware('auth:admin')->group(function (): void {
    // ADM-020 / 021 Orders (all stores)
    Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function (): void {
        Route::middleware('permission:orders.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{fact}', 'show')->whereNumber('fact')->name('show');
            Route::get('{fact}/receipt', 'receipt')->whereNumber('fact')->name('receipt');
        });
        Route::get('export', 'export')->middleware('permission:orders.export,admin')->name('export');
        Route::middleware('permission:orders.manage,admin')->group(function (): void {
            Route::get('{fact}/cancel', 'cancelForm')->whereNumber('fact')->name('cancel.form');
            Route::post('{fact}/cancel', 'cancel')->whereNumber('fact')->name('cancel');
            Route::get('{fact}/company', 'companyForm')->whereNumber('fact')->name('company.form');
            Route::post('{fact}/company/estimate', 'estimate')->whereNumber('fact')->name('company.estimate');
            Route::put('{fact}/company', 'reassign')->whereNumber('fact')->name('company.update');
            Route::post('{fact}/notes', 'note')->whereNumber('fact')->name('notes.store');
        });
    });

    // ADM-022 / 022a Shipping queue (catalog orders the platform ships)
    Route::prefix('shipping-queue')->name('shipping-queue.')->controller(ShippingQueueController::class)->group(function (): void {
        Route::middleware('permission:shipping-queue.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{fact}', 'show')->whereNumber('fact')->name('show');
            Route::get('{fact}/label', 'label')->whereNumber('fact')->name('label');
            Route::get('{fact}/attachments/{attachment}', 'download')->whereNumber(['fact', 'attachment'])->name('attachments.show');
        });
        Route::middleware('permission:shipping-queue.manage,admin')->group(function (): void {
            Route::post('mark-shipped', 'bulkShip')->name('bulk-ship');
            Route::get('{fact}/status', 'statusForm')->whereNumber('fact')->name('status.form');
            Route::put('{fact}/status', 'updateStatus')->whereNumber('fact')->name('status.update');
            Route::post('{fact}/attachments', 'upload')->whereNumber('fact')->name('attachments.store');
            Route::delete('{fact}/attachments/{attachment}', 'destroyAttachment')->whereNumber(['fact', 'attachment'])->name('attachments.destroy');
        });
    });
});
