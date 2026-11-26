<?php

use App\Modules\Engagement\Http\Controllers\Vendor\SubscriberController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.sales.subscribers.*   newsletter subscribers (VEN-026), {subscriber} = row id
*/

Route::middleware('auth:vendor')->prefix('sales/subscribers')->name('sales.subscribers.')->controller(SubscriberController::class)->group(function (): void {
    Route::middleware('permission:subscribers.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::get('export', 'export')->middleware('permission:subscribers.export,vendor')->name('export');
    Route::middleware('permission:subscribers.manage,vendor')->group(function (): void {
        Route::patch('{subscriber}/status', 'status')->whereNumber('subscriber')->name('status');
        Route::delete('{subscriber}', 'destroy')->whereNumber('subscriber')->name('destroy');
    });
});
