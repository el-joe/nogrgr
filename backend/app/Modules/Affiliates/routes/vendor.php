<?php

use App\Modules\Affiliates\Http\Controllers\Vendor\PartnerProgramController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — Partner program (VEN-055 / 055a), names
| "vendor.partner.*": partner.view reads, partner.manage requests payouts and opens the
| affiliate dashboard (SSO).
*/

Route::middleware('auth:vendor')->prefix('partner-program')->name('partner.')->controller(PartnerProgramController::class)->group(function (): void {
    Route::middleware('permission:partner.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('conversions/data', 'conversions')->name('conversions.data');
        Route::get('payouts/data', 'payouts')->name('payouts.data');
    });

    Route::middleware('permission:partner.manage,vendor')->group(function (): void {
        Route::post('withdraw', 'withdraw')->middleware('throttle:10,1')->name('withdraw');
        Route::post('sso', 'sso')->middleware('throttle:20,1')->name('sso');
    });
});
