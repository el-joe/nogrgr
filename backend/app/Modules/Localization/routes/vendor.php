<?php

use App\Modules\Localization\Http\Controllers\Vendor\CurrencySettingsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.currencies*   shopper currencies (VEN-072), display only (USD is charged);
|                                 settings.currencies.view | manage
*/

Route::middleware('auth:vendor')->prefix('settings/currencies')->controller(CurrencySettingsController::class)->group(function (): void {
    Route::get('/', 'index')->middleware('permission:settings.currencies.view,vendor')->name('settings.currencies');
    Route::put('/', 'update')->middleware(['permission:settings.currencies.manage,vendor', 'throttle:30,1'])->name('settings.currencies.update');
});
