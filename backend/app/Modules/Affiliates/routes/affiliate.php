<?php

use App\Modules\Affiliates\Http\Controllers\Affiliate\Auth\RegisterController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\Auth\SsoController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\Auth\VerificationController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\ConversionController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\DashboardController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\LinksController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\PayoutController;
use App\Modules\Affiliates\Http\Controllers\Affiliate\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Surface: affiliate — {central}/affiliate, names "affiliate.*".
*/

/* Guest: partner sign-up (AFF-002 / 003) and "send a new confirmation link" (AFF-006). */
Route::middleware('guest:affiliate')->group(function (): void {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::post('email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.resend');
});

/* Signed / token result pages: work signed in or not; the controller validates them. */
Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware('throttle:12,1')->name('verification.verify');
Route::get('sso/{token}', SsoController::class)->middleware('throttle:20,1')->name('sso');

Route::middleware(['auth:affiliate', 'permission:dashboard.view,affiliate'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('links', [LinksController::class, 'index'])->middleware('permission:links.view,affiliate')->name('links');
    Route::get('links/promo-codes', [LinksController::class, 'promoCodes'])->middleware('permission:links.view,affiliate')->name('links.promo-codes');

    Route::middleware('permission:conversions.view,affiliate')->group(function (): void {
        Route::get('conversions', [ConversionController::class, 'index'])->name('conversions');
        Route::get('conversions/data', [ConversionController::class, 'data'])->name('conversions.data');
    });

    Route::prefix('payouts')->name('payouts.')->group(function (): void {
        Route::middleware('permission:payouts.view,affiliate')->group(function (): void {
            Route::get('/', [PayoutController::class, 'index'])->name('index');
            Route::get('requests/data', [PayoutController::class, 'requestsData'])->name('requests.data');
            Route::get('history/data', [PayoutController::class, 'historyData'])->name('history.data');
        });
        Route::post('requests', [PayoutController::class, 'store'])->middleware(['permission:payouts.request,affiliate', 'throttle:10,1'])->name('requests.store');
    });

    Route::prefix('profile')->name('profile.')->middleware('permission:profile.manage,affiliate')->group(function (): void {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('payout', [ProfileController::class, 'payout'])->name('payout');
        Route::put('password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('password');
    });
});
