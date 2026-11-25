<?php

use App\Modules\Affiliates\Http\Controllers\Affiliate\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Surface: affiliate — {central}/affiliate, names "affiliate.*".
*/

Route::middleware(['auth:affiliate', 'permission:dashboard.view,affiliate'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});
