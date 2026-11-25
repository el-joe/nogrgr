<?php

use App\Modules\Identity\Http\Controllers\Vendor\ImpersonationController;
use App\Modules\Platform\Http\Controllers\Vendor\DashboardController;
use App\Modules\Platform\Http\Controllers\Vendor\OnboardingController;
use App\Modules\Platform\Http\Controllers\Vendor\SetupWizardController;
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

/* Setup wizard (VEN-007, 82:99932): countries → languages → theme → launch. */
Route::middleware(['auth:vendor', 'permission:onboarding.manage,vendor'])->prefix('onboarding/setup')->name('onboarding.setup')->controller(SetupWizardController::class)->group(function (): void {
    Route::get('/', 'show');
    Route::post('countries', 'saveCountries')->middleware('throttle:20,1')->name('.countries');
    Route::post('launch', 'launch')->name('.launch');
});

Route::middleware(['auth:vendor', 'permission:settings.languages.manage,vendor'])->prefix('onboarding/setup/languages')->name('onboarding.setup.languages.')->controller(SetupWizardController::class)->group(function (): void {
    Route::post('{language}/purchase', 'purchaseLanguage')->whereNumber('language')->middleware('throttle:20,1')->name('purchase');
    Route::get('payments/{payment}/result', 'languagePaymentResult')->whereNumber('payment')->name('payment.result');
    Route::get('payments/{payment}/cancel', 'languagePaymentCancel')->whereNumber('payment')->name('payment.cancel');
});

/* Impersonation "End session" (VEN-005): sign-out-like, only needs the vendor session. */
Route::middleware('auth:vendor')->post('impersonation/leave', [ImpersonationController::class, 'leave'])->name('impersonation.leave');
