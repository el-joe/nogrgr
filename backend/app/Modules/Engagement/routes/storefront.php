<?php

use App\Modules\Engagement\Http\Controllers\Storefront\FavoriteController;
use App\Modules\Engagement\Http\Controllers\Storefront\SupportController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — {store-domain}/, names "storefront.*", tenant DB.
| Favorites JSON (SF-ECO-033; the page is storefront.favorites by sf-commerce), support
| form + newsletter signup (SF-ECO-035).
*/

Route::prefix('favorites')->name('favorites.')->controller(FavoriteController::class)->group(function (): void {
    Route::post('toggle', 'toggle')->middleware('throttle:60,1')->name('toggle');
    Route::get('ids', 'ids')->name('ids');
});

Route::post('support', [SupportController::class, 'store'])->middleware('throttle:storefront-forms')->name('support.store');
Route::post('newsletter', [SupportController::class, 'newsletter'])->middleware('throttle:storefront-forms')->name('newsletter.store');
