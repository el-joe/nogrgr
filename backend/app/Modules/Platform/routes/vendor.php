<?php

use App\Modules\Identity\Http\Controllers\Vendor\ImpersonationController;
use App\Modules\Platform\Http\Controllers\Vendor\DashboardController;
use App\Modules\Platform\Http\Controllers\Vendor\OnboardingController;
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
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
});

Route::middleware(['auth:vendor', 'permission:onboarding.manage,vendor'])->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::post('tour/complete', [OnboardingController::class, 'completeTour'])->name('tour.complete');
    Route::post('tour/dismiss', [OnboardingController::class, 'dismissTour'])->name('tour.dismiss');
    Route::post('payment-readiness/skip', [OnboardingController::class, 'skipPaymentReadiness'])->name('payment-readiness.skip');
});

Route::middleware(['auth:vendor', 'permission:settings.store.manage,vendor', 'throttle:20,1'])
    ->post('onboarding/logo', [OnboardingController::class, 'uploadLogo'])
    ->name('onboarding.logo');

/* Impersonation "End session" (VEN-005): sign-out-like, only needs the vendor session. */
Route::middleware('auth:vendor')->post('impersonation/leave', [ImpersonationController::class, 'leave'])->name('impersonation.leave');
