<?php

use App\Modules\Storefront\Http\Controllers\Storefront\Account\FavoritesController;
use App\Modules\Storefront\Http\Controllers\Storefront\Account\OrderController;
use App\Modules\Storefront\Http\Controllers\Storefront\Account\ReturnController;
use Illuminate\Support\Facades\Route;

/*
| Customer account pages of the theme (sf-account, P7): orders, order details / tracking /
| invoice, returns, wishlist. Signed-in customers only (guests → account login); other
| customers' orders and returns → 404. Required from routes/storefront.php.
*/

Route::middleware('auth:customer')->group(function (): void {
    Route::get('account/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('orders.show');
    Route::get('orders/{order}/tracking', [OrderController::class, 'tracking'])->whereUuid('order')->name('orders.tracking');
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->whereUuid('order')->name('orders.invoice');
    Route::get('orders/{order}/return', [ReturnController::class, 'create'])->whereUuid('order')->name('returns.create');

    Route::get('account/returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('account/returns/{return}', [ReturnController::class, 'show'])->whereNumber('return')->name('returns.show');

    Route::get('account/wishlist', FavoritesController::class)->name('favorites');
});

// Signed invoice link for the app (Store API orders/{order}/invoice): no session needed.
Route::get('orders/{order}/invoice/print', [OrderController::class, 'signedInvoice'])->whereUuid('order')->middleware(['signed:relative', 'throttle:30,1'])->name('orders.invoice.signed');

Route::redirect('favorites', '/account/wishlist')->name('favorites.alias');
