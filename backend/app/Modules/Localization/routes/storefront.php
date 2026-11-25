<?php

use App\Modules\Localization\Http\Controllers\Storefront\CountryController;
use App\Modules\Localization\Http\Controllers\Storefront\GeocodingController;
use Illuminate\Support\Facades\Route;

/*
| Address form helpers on the store host (JSON): store countries, city suggestions,
| reverse geocoding and IP location. Public (checkout + account), rate limited.
*/

Route::prefix('geo')->name('geo.')->group(function (): void {
    Route::get('countries', [CountryController::class, 'index'])->name('countries');
    Route::get('countries/{country}/cities', [CountryController::class, 'cities'])->whereNumber('country')->name('cities');

    Route::middleware('throttle:geocoding')->group(function (): void {
        Route::get('reverse', [GeocodingController::class, 'reverse'])->name('reverse');
        Route::get('ip', [GeocodingController::class, 'ip'])->name('ip');
    });
});
