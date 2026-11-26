<?php

use App\Modules\Identity\Http\Controllers\Vendor\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\Vendor\LoginController;
use App\Modules\Identity\Http\Controllers\Vendor\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor-auth — store host only: {store-domain}/login, names "vendor.*".
| On the central host the same names resolve to /tenant/login (see VendorPanel::CENTRAL_AUTH_ROUTES).
| Link with vendor_route('login'); never name vendor.* routes directly.
*/

Route::middleware('guest:vendor')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
    /* Staff invitation (VEN-076a, QA-P8-008): same broker token, welcome / set-password copy. */
    Route::get('invitation/{token}', [PasswordResetController::class, 'invitation'])->name('invitation.accept');
});

/* Signed result page: works signed in or not; the controller checks the signature (expired state). */
Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:12,1')
    ->name('verification.verify');

Route::middleware('auth:vendor')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware(['permission:dashboard.view,vendor', 'throttle:6,1'])
        ->name('verification.send');
});
