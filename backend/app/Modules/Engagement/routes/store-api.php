<?php

use App\Modules\Engagement\Http\Controllers\StoreApi\ReviewController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.*". Product reviews
| (SF-ECO-019a list + write, SF-ECO-028d write from an order line). Writing needs a
| signed-in customer (401) with a delivered order line of the product (422).
*/

Route::prefix('products/{storeProduct}/reviews')->whereNumber('storeProduct')->name('products.reviews.')->controller(ReviewController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:storefront-reviews')->name('store');
});

Route::post('orders/{order}/items/{item}/review', [ReviewController::class, 'storeForItem'])
    ->whereUuid('order')
    ->whereNumber('item')
    ->middleware('throttle:storefront-reviews')
    ->name('orders.items.review');
