<?php

use App\Modules\Shipping\Http\Controllers\Admin\DayRuleController;
use App\Modules\Shipping\Http\Controllers\Admin\DeliveryPopupController;
use App\Modules\Shipping\Http\Controllers\Admin\FixedShippingCostController;
use App\Modules\Shipping\Http\Controllers\Admin\ShippingCompanyController;
use App\Modules\Shipping\Http\Controllers\Admin\ShippingRuleController;
use App\Modules\Shipping\Http\Controllers\Admin\ShippingSettingsController;
use App\Modules\Shipping\Http\Controllers\Admin\StopPeriodController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/shipping/*, names "admin.shipping.*" (ADM-031…035).
*/

Route::middleware('auth:admin')->prefix('shipping')->name('shipping.')->group(function (): void {
    // ADM-031 / 031a Fixed shipping costs
    Route::prefix('fixed-costs')->name('fixed-costs.')->controller(FixedShippingCostController::class)->group(function (): void {
        Route::middleware('permission:shipping.fixed-costs.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('calculate', 'calculate')->name('calculate');
        });
        Route::middleware('permission:shipping.fixed-costs.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{fixedCost}/edit', 'edit')->whereNumber('fixedCost')->name('edit');
            Route::put('{fixedCost}', 'update')->whereNumber('fixedCost')->name('update');
            Route::delete('{fixedCost}', 'destroy')->whereNumber('fixedCost')->name('destroy');
        });
    });

    // ADM-032 / 032a Shipping rules / zones
    Route::prefix('rules')->name('rules.')->controller(ShippingRuleController::class)->group(function (): void {
        Route::middleware('permission:shipping.rules.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });
        Route::middleware('permission:shipping.rules.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('cities', 'cities')->name('cities');
            Route::get('{rule}/edit', 'edit')->whereNumber('rule')->name('edit');
            Route::put('{rule}', 'update')->whereNumber('rule')->name('update');
            Route::delete('{rule}', 'destroy')->whereNumber('rule')->name('destroy');
        });
    });

    // ADM-033 / 033a–d Shipping companies, day rules, estimator
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::controller(ShippingCompanyController::class)->group(function (): void {
            Route::middleware('permission:shipping.companies.view,admin')->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('data', 'data')->name('data');
                Route::get('estimate', 'estimate')->name('estimate');
                Route::get('{company}', 'show')->whereNumber('company')->name('show');
            });
            Route::middleware('permission:shipping.companies.manage,admin')->group(function (): void {
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{company}/edit', 'edit')->whereNumber('company')->name('edit');
                Route::put('{company}', 'update')->whereNumber('company')->name('update');
                Route::patch('{company}/active', 'toggle')->whereNumber('company')->name('toggle');
                Route::delete('{company}', 'destroy')->whereNumber('company')->name('destroy');
            });
        });

        Route::middleware('permission:shipping.companies.manage,admin')->prefix('{company}/day-rules')->whereNumber('company')->name('day-rules.')->controller(DayRuleController::class)->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{dayRule}/edit', 'edit')->whereNumber('dayRule')->name('edit');
            Route::put('{dayRule}', 'update')->whereNumber('dayRule')->name('update');
            Route::delete('{dayRule}', 'destroy')->whereNumber('dayRule')->name('destroy');
        });
    });

    // ADM-033c Stop periods
    Route::prefix('stop-periods')->name('stop-periods.')->controller(StopPeriodController::class)->group(function (): void {
        Route::get('/', 'index')->middleware('permission:shipping.stop-periods.view,admin')->name('index');
        Route::middleware('permission:shipping.stop-periods.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{stopPeriod}/edit', 'edit')->whereNumber('stopPeriod')->name('edit');
            Route::put('{stopPeriod}', 'update')->whereNumber('stopPeriod')->name('update');
            Route::delete('{stopPeriod}', 'destroy')->whereNumber('stopPeriod')->name('destroy');
        });
    });

    // ADM-035 Delivery popup days
    Route::prefix('delivery-popup')->name('delivery-popup.')->controller(DeliveryPopupController::class)->group(function (): void {
        Route::get('/', 'index')->middleware('permission:shipping.delivery-popup.view,admin')->name('index');
        Route::middleware('permission:shipping.delivery-popup.manage,admin')->group(function (): void {
            Route::put('/', 'update')->name('update');
            Route::delete('/', 'reset')->name('reset');
        });
    });

    // ADM-034 Shipping settings
    Route::prefix('settings')->controller(ShippingSettingsController::class)->group(function (): void {
        Route::get('/', 'edit')->middleware('permission:shipping.settings.view,admin')->name('settings');
        Route::middleware('permission:shipping.settings.manage,admin')->name('settings.')->group(function (): void {
            Route::put('/', 'update')->name('update');
            Route::post('defaults', 'storeDefault')->name('defaults.store');
            Route::delete('defaults/{country}', 'destroyDefault')->whereNumber('country')->name('defaults.destroy');
        });
    });
});
