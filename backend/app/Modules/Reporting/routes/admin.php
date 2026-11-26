<?php

use App\Modules\Reporting\Http\Controllers\Admin\ReportController;
use App\Modules\Reporting\Http\Controllers\Admin\ShopperController;
use App\Modules\Reporting\Http\Controllers\Admin\VendorStatsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Reporting: Reports hub + tabs (ADM-070…070k), Shoppers across stores
| (ADM-043) and the vendor profile "Stats & orders" table (ADM-041e).
| Every route: auth:admin + permission (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware('auth:admin')->group(function (): void {
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function (): void {
        Route::middleware('permission:reports.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{tab}', 'show')->name('show');
            Route::get('{tab}/data', 'data')->name('data');
        });

        Route::get('{tab}/export', 'export')->middleware(['permission:reports.export,admin', 'throttle:20,1'])->name('export');
    });

    Route::prefix('vendors/shoppers')->name('vendors.shoppers.')->controller(ShopperController::class)->group(function (): void {
        Route::middleware('permission:vendors.shoppers.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::get('export', 'export')->middleware(['permission:vendors.shoppers.export,admin', 'throttle:20,1'])->name('export');
    });

    Route::get('vendors/{tenant}/orders/data', VendorStatsController::class)
        ->whereNumber('tenant')
        ->middleware('permission:vendors.view,admin')
        ->name('vendors.stats.orders');
});
