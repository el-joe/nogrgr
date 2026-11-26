<?php

use App\Modules\Commerce\Http\Controllers\StoreApi\CartController;
use App\Modules\Commerce\Http\Controllers\StoreApi\CheckoutController;
use App\Modules\Commerce\Http\Controllers\StoreApi\OrderController;
use App\Modules\Commerce\Http\Controllers\StoreApi\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.*". Tenant from the store
| domain (themes, session + CSRF) or the X-Tenant header (mobile, Sanctum token).
| Guests: X-Cart-Token. Checkout, orders and payments need a signed-in customer (401).
*/

Route::prefix('cart')->name('cart.')->controller(CartController::class)->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::post('items', 'store')->name('items.store');
    Route::patch('items/{item}', 'update')->whereNumber('item')->name('items.update');
    Route::delete('items/{item}', 'destroy')->whereNumber('item')->name('items.destroy');
    Route::post('coupon', 'applyCoupon')->name('coupon.apply');
    Route::delete('coupon', 'removeCoupon')->name('coupon.remove');
    Route::put('country', 'country')->name('country');
});

Route::prefix('checkout')->name('checkout.')->controller(CheckoutController::class)->group(function (): void {
    Route::get('options', 'options')->name('options');
    Route::post('place', 'place')->middleware('throttle:store-api-checkout')->name('place');
});

Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('cancellation-reasons', 'cancellationReasons')->name('cancellation-reasons');
    Route::get('{order}', 'show')->whereUuid('order')->name('show');
    Route::post('{order}/cancel', 'cancel')->whereUuid('order')->name('cancel');
    Route::post('{order}/reorder', 'reorder')->whereUuid('order')->name('reorder');
    Route::get('{order}/invoice', 'invoice')->whereUuid('order')->name('invoice');
});

Route::prefix('payments')->name('payments.')->controller(PaymentController::class)->group(function (): void {
    Route::post('{order}/start', 'start')->whereUuid('order')->middleware('throttle:store-api-checkout')->name('start');
    Route::post('{order}/confirm', 'confirm')->whereUuid('order')->name('confirm');
    Route::post('webhooks/{gateway}', 'webhook')->where('gateway', '[a-z_]+')->withoutMiddleware('throttle:store-api')->name('webhook');
});
