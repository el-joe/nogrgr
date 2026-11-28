<?php

use App\Modules\AfterSales\Http\Controllers\StoreApi\ReturnController;
use App\Modules\Api\Http\Middleware\EnsureSignedStoreLink;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — customer returns (SF-ECO-030/031/032, APP-016/017/018), signed-in
| customer only (401 JSON); own returns of the current store only (404). Evidence files
| are served behind temporary relative signed URLs from the return detail.
*/

Route::controller(ReturnController::class)->group(function (): void {
    Route::prefix('orders/{order}')->whereUuid('order')->name('orders.returns.')->group(function (): void {
        Route::get('returns/options', 'options')->name('options');
        Route::post('returns/estimate', 'estimate')->name('estimate');
        Route::get('returns', 'forOrder')->name('index');
        Route::post('returns', 'store')->name('store');
    });

    Route::prefix('returns')->name('returns.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{return}', 'show')->whereNumber('return')->name('show');
        Route::post('{return}/messages', 'reply')->whereNumber('return')->name('messages.store');
        Route::post('{return}/withdraw', 'withdraw')->whereNumber('return')->name('withdraw');
        Route::get('{return}/media/{media}', 'media')->whereNumber(['return', 'media'])->middleware(['signed:relative', EnsureSignedStoreLink::class])->name('media');
    });
});
