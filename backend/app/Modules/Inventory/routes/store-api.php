<?php

use App\Modules\Inventory\Http\Controllers\StoreApi\StockAlertController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.*". Back-in-stock subscriptions
| (decision #124): GET = is the signed-in customer subscribed; POST = subscribe (guest
| e-mail required, signed-in customer uses the account e-mail). 422 when the product is
| unknown or in stock.
*/

Route::prefix('products/{storeProduct}/stock-alerts')->whereNumber('storeProduct')->name('products.stock-alerts.')->controller(StockAlertController::class)->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
});
