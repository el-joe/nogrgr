<?php

use App\Modules\Identity\Http\Controllers\Admin\AccountController;
use App\Modules\Identity\Http\Controllers\Admin\AdminController;
use App\Modules\Identity\Http\Controllers\Admin\AuditLogController;
use App\Modules\Identity\Http\Controllers\Admin\ImpersonateVendorController;
use App\Modules\Identity\Http\Controllers\Admin\LoginController;
use App\Modules\Identity\Http\Controllers\Admin\PasswordResetController;
use App\Modules\Identity\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

/*
| Guest-only auth routes (allow-listed in tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware('auth:admin')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth:admin', 'permission:tenants.impersonate,admin'])->group(function (): void {
    Route::post('tenants/{tenant}/impersonate', ImpersonateVendorController::class)->name('tenants.impersonate');
});

/*
| My profile (ADM-005): every signed-in admin edits their own account.
*/
Route::middleware(['auth:admin', 'can:manage-own-account'])->controller(AccountController::class)->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', 'edit')->name('edit');
    Route::put('/', 'update')->name('update');
    Route::put('password', 'password')->middleware('throttle:6,1')->name('password');
});

/*
| System › Admins & roles (ADM-110 / 111): `*.view` reads, `*.manage` changes.
*/
Route::middleware('auth:admin')->prefix('system')->name('system.')->group(function (): void {
    Route::controller(AdminController::class)->prefix('admins')->name('admins.')->group(function (): void {
        Route::middleware('permission:admins.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:admins.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{admin}/edit', 'edit')->whereNumber('admin')->name('edit');
            Route::put('{admin}', 'update')->whereNumber('admin')->name('update');
            Route::patch('{admin}/active', 'toggle')->whereNumber('admin')->name('toggle');
            Route::delete('{admin}', 'destroy')->whereNumber('admin')->name('destroy');
        });
    });

    Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function (): void {
        Route::middleware('permission:roles.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:roles.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{role}/edit', 'edit')->whereNumber('role')->name('edit');
            Route::put('{role}', 'update')->whereNumber('role')->name('update');
            Route::delete('{role}', 'destroy')->whereNumber('role')->name('destroy');
        });
    });
});

/*
| System › Audit log (ADM-112): `audit.view` reads, `audit.export` downloads CSV.
*/
Route::middleware('auth:admin')->prefix('system/audit')->name('system.audit.')->controller(AuditLogController::class)->group(function (): void {
    Route::middleware('permission:audit.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('tenants', 'tenants')->name('tenants');
        Route::get('{event}', 'show')->whereNumber('event')->name('show');
    });

    Route::get('export', 'export')->middleware(['permission:audit.export,admin', 'throttle:10,1'])->name('export');
});
