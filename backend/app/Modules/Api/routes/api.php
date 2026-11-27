<?php

use App\Modules\Api\Http\Controllers\Api\PingController;
use App\Modules\Api\Http\Controllers\Mobile\AddressController;
use App\Modules\Api\Http\Controllers\Mobile\AuthController;
use App\Modules\Api\Http\Controllers\Mobile\BootstrapController;
use App\Modules\Api\Http\Controllers\Mobile\DeviceController;
use App\Modules\Api\Http\Controllers\Mobile\EmailVerificationController;
use App\Modules\Api\Http\Controllers\Mobile\FavoriteController;
use App\Modules\Api\Http\Controllers\Mobile\NotificationController;
use App\Modules\Api\Http\Controllers\Mobile\PasswordResetController;
use App\Modules\Api\Http\Controllers\Mobile\ProfileController;
use App\Modules\Api\Http\Middleware\AuthenticateMobileCustomer;
use App\Modules\Api\Http\Middleware\SetMobileLocale;
use Illuminate\Support\Facades\Route;

/*
| Surface: api — /api/mobile/v1, names "api.mobile.v1.*", tenant from the X-Tenant header
| (store DB). The white-label app's own endpoints: auth (Sanctum access + refresh tokens),
| bootstrap + static texts, account, addresses, wishlist, inbox, push devices. Catalog,
| cart, checkout, payments, orders, returns, reviews and image search are the Store API
| (/store-api/v1) with the same bearer token. Reference: docs/plan/mobile-api.md.
| Errors: {success: false, message} (+ 422 {message, errors}); locale X-Locale.
*/

Route::middleware([SetMobileLocale::class, 'throttle:mobile-api'])->group(function (): void {
    Route::get('ping', PingController::class)->name('ping');

    Route::get('bootstrap', [BootstrapController::class, 'show'])->name('bootstrap');
    Route::get('texts', [BootstrapController::class, 'texts'])->name('texts');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:mobile-auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::post('social/{provider}', [AuthController::class, 'social'])->whereIn('provider', ['google', 'apple'])->name('social');
            Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
            Route::post('password/check', [PasswordResetController::class, 'check'])->name('password.check');
            Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        Route::middleware(AuthenticateMobileCustomer::class)->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('email/verification', [EmailVerificationController::class, 'status'])->name('verification.status');
            Route::post('email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.resend');
        });
    });

    Route::middleware(AuthenticateMobileCustomer::class.':optional')->group(function (): void {
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->where('device', '[A-Za-z0-9._:-]{8,100}')->name('devices.destroy');
    });

    Route::middleware(AuthenticateMobileCustomer::class)->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');

        Route::prefix('account')->name('account.')->group(function (): void {
            Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('password.update');
            Route::get('notification-preferences', [ProfileController::class, 'preferences'])->name('preferences');
            Route::put('notification-preferences', [ProfileController::class, 'updatePreferences'])->name('preferences.update');
            Route::delete('/', [ProfileController::class, 'destroy'])->middleware('throttle:6,1')->name('destroy');

            Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
            Route::post('addresses/{address}/default', [AddressController::class, 'setDefault'])->whereNumber('address')->name('addresses.default');
            Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
        });

        Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('favorites', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('favorites/{storeProduct}', [FavoriteController::class, 'destroy'])->whereNumber('storeProduct')->name('favorites.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->whereUuid('notification')->name('notifications.destroy');
    });
});
