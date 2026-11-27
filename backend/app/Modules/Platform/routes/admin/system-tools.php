<?php

use App\Modules\Platform\Http\Controllers\Admin\CacheToolsController;
use App\Modules\Platform\Http\Controllers\Admin\MaintenanceWindowController;
use Illuminate\Support\Facades\Route;

/*
| Admin › System tools › Maintenance windows (ADM-113) and Cache tools (ADM-114 / 114a / 114b).
*/

Route::middleware('auth:admin')->prefix('system')->name('system.')->group(function (): void {
    Route::prefix('maintenance')->name('maintenance.')->controller(MaintenanceWindowController::class)->group(function (): void {
        Route::middleware('permission:system.maintenance.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:system.maintenance.manage,admin')->group(function (): void {
            Route::get('stores', 'stores')->middleware('throttle:60,1')->name('stores');
            Route::post('/', 'store')->name('store');
            Route::put('{window}', 'update')->whereNumber('window')->name('update');
            Route::post('{window}/end', 'end')->whereNumber('window')->name('end');
            Route::delete('{window}', 'destroy')->whereNumber('window')->name('destroy');
        });
    });

    Route::prefix('cache')->name('cache.')->controller(CacheToolsController::class)->group(function (): void {
        Route::middleware('permission:system.cache.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('stores', 'stores')->middleware('throttle:60,1')->name('stores');
        });

        Route::post('clear', 'clear')->middleware(['permission:system.cache.manage,admin', 'throttle:20,1'])->name('clear');
    });
});
