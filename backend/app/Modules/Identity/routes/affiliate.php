<?php

use App\Modules\Identity\Http\Controllers\Affiliate\LoginController;
use App\Modules\Identity\Http\Controllers\Affiliate\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
| Guest-only auth routes (allow-listed in tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware('guest:affiliate')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware('auth:affiliate')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
