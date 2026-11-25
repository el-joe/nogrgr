<?php

use App\Modules\Commerce\Http\Controllers\Admin\CouponController;
use App\Modules\Commerce\Http\Controllers\Admin\FlashSaleController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/promotions/*, names "admin.promotions.*" (ADM-025, 026).
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
