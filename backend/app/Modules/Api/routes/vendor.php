<?php

use App\Modules\Api\Http\Controllers\Vendor\MobileAppController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — Your store › Mobile app (VEN-037 / 037a):
| vendor.store.mobile-app (store.mobile-app.view) and .update (store.mobile-app.manage).
*/

Route::middleware('auth:vendor')->prefix('store/mobile-app')->name('store.mobile-app')->controller(MobileAppController::class)->group(function (): void {
    Route::get('/', 'show')->middleware('permission:store.mobile-app.view,vendor');
    Route::put('/', 'update')->middleware(['permission:store.mobile-app.manage,vendor', 'throttle:30,1'])->name('.update');
});
