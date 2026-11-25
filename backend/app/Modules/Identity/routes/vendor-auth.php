<?php

use App\Modules\Identity\Http\Controllers\Vendor\LoginController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor-auth — store host only: {store-domain}/login, names "vendor.*".
| On the central host the same names resolve to /tenant/login (see VendorPanel::CENTRAL_AUTH_ROUTES).
| Link with vendor_route('login'); never name vendor.* routes directly.
*/

Route::middleware('guest:vendor')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth:vendor')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
