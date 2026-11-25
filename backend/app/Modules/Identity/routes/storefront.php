<?php

use App\Modules\Identity\Http\Controllers\Storefront\AddressController;
use App\Modules\Identity\Http\Controllers\Storefront\LoginController;
use App\Modules\Identity\Http\Controllers\Storefront\NotificationPreferencesController;
use App\Modules\Identity\Http\Controllers\Storefront\PasswordResetController;
use App\Modules\Identity\Http\Controllers\Storefront\ProfileController;
use App\Modules\Identity\Http\Controllers\Storefront\RegisterController;
use Illuminate\Support\Facades\Route;

/*
| Storefront customer account on the store host: {store-domain}/account/...
| (`/login` on a store host belongs to the vendor panel). Names "storefront.account.*".
| Login throttling (5/min per email+IP) is in SessionAuthService.
*/

Route::prefix('account')->name('account.')->group(function (): void {
    Route::middleware('guest:customer')->group(function (): void {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
    });

    Route::middleware('auth:customer')->group(function (): void {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('password', [ProfileController::class, 'password'])->name('password.update');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('destroy');

        Route::get('notifications', [NotificationPreferencesController::class, 'edit'])->name('notifications');
        Route::put('notifications', [NotificationPreferencesController::class, 'update'])->name('notifications.update');

        Route::get('addresses', [AddressController::class, 'index'])->name('addresses');
        Route::get('addresses/list', [AddressController::class, 'list'])->name('addresses.list');
        Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::put('addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
        Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
        Route::post('addresses/{address}/default', [AddressController::class, 'setDefault'])->whereNumber('address')->name('addresses.default');
    });
});
