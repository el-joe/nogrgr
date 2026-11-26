<?php

use App\Modules\Platform\Http\Controllers\Admin\CheckoutDemoController;
use App\Modules\Platform\Http\Controllers\Admin\DashboardController;
use App\Modules\Platform\Http\Controllers\Admin\MotionDemoController;
use App\Modules\Platform\Http\Controllers\Admin\UiKitController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin, names "admin.*".
| Every route needs auth:admin + a permission (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware(['auth:admin', 'permission:dashboard.view,admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth:admin', 'permission:system.ui-kit.view,admin'])->group(function (): void {
    Route::get('ui-kit', [UiKitController::class, 'show'])->name('ui-kit');
    Route::get('ui-kit/table', [UiKitController::class, 'table'])->name('ui-kit.table');
    Route::post('ui-kit/bulk', [UiKitController::class, 'bulk'])->name('ui-kit.bulk');
    Route::post('ui-kit/form', [UiKitController::class, 'submit'])->name('ui-kit.form');
    Route::get('ui-kit/storefront-motion', MotionDemoController::class)->name('ui-kit.storefront-motion');

    Route::controller(CheckoutDemoController::class)->prefix('ui-kit/storefront-checkout')->name('ui-kit.checkout.')->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('addresses', 'store')->name('store');
        Route::put('addresses/{address}', 'update')->whereNumber('address')->name('update');
        Route::delete('addresses/{address}', 'destroy')->whereNumber('address')->name('destroy');
        Route::post('addresses/{address}/default', 'setDefault')->whereNumber('address')->name('default');
        Route::get('countries/{country}/cities', 'cities')->whereNumber('country')->name('cities');
        Route::get('geo/reverse', 'reverse')->name('reverse');
        Route::get('geo/ip', 'ip')->name('ip');
        Route::post('pay', 'pay')->name('pay');
    });
});

// Vendors (P2): list / create / profile, pending registrations, compliance, domains.
require __DIR__.'/admin/vendors.php';
require __DIR__.'/admin/registrations.php';
require __DIR__.'/admin/compliance.php';
require __DIR__.'/admin/domains.php';

// System tools › Developer docs (P9, ADM-115 / 115a).
require __DIR__.'/admin/developer-docs.php';
