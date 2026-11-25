<?php

use App\Modules\Platform\Http\Controllers\Owner\AddStoreController;
use App\Modules\Platform\Http\Controllers\Owner\OwnerDashboardController;
use App\Modules\Platform\Http\Controllers\Owner\OwnerDomainController;
use App\Modules\Platform\Http\Controllers\Owner\OwnerSelectStoreController;
use App\Modules\Platform\Http\Controllers\Owner\OwnerSessionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — {central}/my-account, names "owner.*" (guard "owner").
| Every route: auth:owner + a gate (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware(['auth:owner', 'can:owner.stores.create'])->group(function (): void {
    Route::get('stores/create', [AddStoreController::class, 'create'])->name('stores.create');
    Route::post('stores', [AddStoreController::class, 'store'])->middleware('throttle:10,1')->name('stores.store');
    Route::get('stores/availability', [AddStoreController::class, 'availability'])->middleware('throttle:30,1')->name('stores.availability');
});

Route::middleware(['auth:owner', 'can:owner.portal'])->group(function (): void {
    // My stores (OWN-001) + open a store panel (SSO)
    Route::get('/', [OwnerDashboardController::class, 'index'])->name('dashboard');
    Route::post('stores/{tenant}/open', [OwnerDashboardController::class, 'open'])->whereNumber('tenant')->middleware('throttle:30,1')->name('stores.open');

    // Select store after sign-in (OWN-002)
    Route::get('select-store', [OwnerSelectStoreController::class, 'show'])->name('select-store');
    Route::post('select-store', [OwnerSelectStoreController::class, 'store'])->middleware('throttle:30,1')->name('select-store.store');

    // Domains (OWN-004 / 004a / 004b / 004c)
    Route::prefix('domains')->name('domains.')->controller(OwnerDomainController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('instructions/{tenant}', 'instructions')->whereNumber('tenant')->name('instructions');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('{domain}', 'show')->whereNumber('domain')->name('show');
        Route::get('{domain}/edit', 'edit')->whereNumber('domain')->name('edit');
        Route::put('{domain}', 'update')->whereNumber('domain')->middleware('throttle:20,1')->name('update');
        Route::post('{domain}/check', 'check')->whereNumber('domain')->middleware('throttle:10,1')->name('check');
        Route::delete('{domain}', 'destroy')->whereNumber('domain')->name('destroy');
    });
});

Route::post('logout', OwnerSessionController::class)->middleware('auth:owner')->name('logout');
