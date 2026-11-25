<?php

use App\Modules\Identity\Http\Controllers\Central\StorePickerController;
use App\Modules\Identity\Http\Controllers\Central\TenantLoginController;
use Illuminate\Support\Facades\Route;

/*
| Central-host vendor entry: {central}/tenant/login (+ store picker, logout).
| Registered before the /tenant/{store} panel mount; these words are reserved slugs.
*/

Route::prefix('tenant')->name('tenant.')->group(function (): void {
    Route::get('login', [TenantLoginController::class, 'create'])->name('login');
    Route::post('login', [TenantLoginController::class, 'store'])->name('login.store');
    Route::get('stores', [StorePickerController::class, 'index'])->name('stores');
    Route::post('stores', [StorePickerController::class, 'store'])->name('stores.select');
    Route::post('logout', [TenantLoginController::class, 'destroy'])->name('logout');
});
