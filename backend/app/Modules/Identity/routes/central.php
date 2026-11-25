<?php

use App\Modules\Identity\Http\Controllers\Central\ImpersonationAcceptController;
use App\Modules\Identity\Http\Controllers\Central\SocialAuthController;
use App\Modules\Identity\Http\Controllers\Central\StoreLoginPasswordController;
use App\Modules\Identity\Http\Controllers\Central\StorePickerController;
use App\Modules\Identity\Http\Controllers\Central\TenantLoginController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
| Central-host vendor entry: {central}/tenant/login (+ store picker, logout).
| Registered before the /tenant/{store} panel mount; these words are reserved slugs.
*/

/*
| Store login on the central website (WEB-022 … 024): /login shows the same form as
| /tenant/login; forgot / reset passwords of the store accounts sharing the e-mail.
*/

Route::get('login', [TenantLoginController::class, 'create'])->name('login');
Route::get('forgot-password', [StoreLoginPasswordController::class, 'request'])->name('password.request');
Route::post('forgot-password', [StoreLoginPasswordController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
Route::get('reset-password/{token}', [StoreLoginPasswordController::class, 'edit'])->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
Route::post('reset-password', [StoreLoginPasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

Route::prefix('tenant')->name('tenant.')->group(function (): void {
    Route::get('login', [TenantLoginController::class, 'create'])->name('login');
    Route::post('login', [TenantLoginController::class, 'store'])->name('login.store');
    Route::get('stores', [StorePickerController::class, 'index'])->name('stores');
    Route::post('stores', [StorePickerController::class, 'store'])->name('stores.select');
    Route::post('logout', [TenantLoginController::class, 'destroy'])->name('logout');
    Route::get('impersonate/{token}', ImpersonationAcceptController::class)->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:20,1')->name('impersonate');
});

/*
| Google / Apple for store owners (WEB-016a). The callback path is fixed
| (Integrations\Support\SocialRedirectUri); Apple posts it cross-site (CSRF-exempt,
| protected by the state + nonce kept in the session).
*/

Route::prefix('auth/{provider}')->where(['provider' => 'google|apple'])->name('social.')->group(function (): void {
    Route::get('redirect', [SocialAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('redirect');
    Route::get('callback', [SocialAuthController::class, 'callback'])->middleware('throttle:30,1')->name('callback');
    Route::post('callback', [SocialAuthController::class, 'callbackPost'])
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware('throttle:30,1')
        ->name('callback.post');
});
