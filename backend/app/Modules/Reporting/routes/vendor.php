<?php

use App\Modules\Reporting\Http\Controllers\Vendor\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — analytics of the current store:
|   vendor.analytics, .data, .export              VEN-045 tabs (?tab=orders|clv|shipping|profitability); analytics.view
|   vendor.sales.returns.analytics, .data, .export  VEN-024 return analytics; returns.view
| Link with vendor_route(); data from the central Reporting facts of this store only.
*/

Route::middleware('auth:vendor')->controller(AnalyticsController::class)->group(function (): void {
    Route::middleware('permission:analytics.view,vendor')->prefix('analytics')->group(function (): void {
        Route::get('/', 'show')->name('analytics');
        Route::get('{tab}/data', 'data')->whereAlpha('tab')->name('analytics.data');
        Route::get('{tab}/export', 'export')->whereAlpha('tab')->middleware('throttle:20,1')->name('analytics.export');
    });

    Route::middleware('permission:returns.view,vendor')->prefix('sales/returns/analytics')->name('sales.returns.')->group(function (): void {
        Route::get('/', 'returns')->name('analytics');
        Route::get('data', 'returnsData')->name('analytics.data');
        Route::get('export', 'returnsExport')->middleware('throttle:20,1')->name('analytics.export');
    });
});
