<?php

use App\Modules\Inventory\Http\Controllers\Vendor\StockAlertSettingsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.notifications.stock-threshold   store-wide low-stock alert threshold
|                                                   (card on VEN-087); catalog.products.manage
*/

Route::middleware(['auth:vendor', 'permission:catalog.products.manage,vendor'])
    ->put('settings/notifications/stock-threshold', [StockAlertSettingsController::class, 'update'])
    ->name('settings.notifications.stock-threshold');
