<?php

use App\Modules\Platform\Http\Controllers\Vendor\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel — mounted twice from this one file:
|   store host    {store-domain}/admin/...     (names vendor.*)
|   central host  {central}/tenant/{store}/... (names vendor-central.*)
| Link with vendor_route('dashboard'); never name vendor.* routes directly.
*/

Route::middleware(['auth:vendor', 'permission:dashboard.view,vendor'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});
