<?php

use App\Modules\Identity\Http\Controllers\Vendor\AccountController;
use App\Modules\Identity\Http\Controllers\Vendor\AuditLogController;
use App\Modules\Identity\Http\Controllers\Vendor\RoleController;
use App\Modules\Identity\Http\Controllers\Vendor\StaffController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.staff.*   store team (VEN-076 / 076a), {staff} = vendor_staff id
|   vendor.settings.roles.*   store roles & permissions (VEN-077 / 077a), {role} = role id
|   vendor.settings.account*  the signed-in member's own account (VEN-070); dashboard.view
|   vendor.settings.audit*    store audit log (VEN-086); settings.audit.view
| `*.view` reads, `*.manage` changes. Owners and the "owner" role are untouchable.
*/

Route::middleware('auth:vendor')->prefix('settings')->name('settings.')->group(function (): void {
    Route::prefix('staff')->name('staff.')->controller(StaffController::class)->group(function (): void {
        Route::middleware('permission:settings.staff.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{staff}', 'show')->whereNumber('staff')->name('show');
        });
        Route::middleware('permission:settings.staff.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            Route::get('{staff}/edit', 'edit')->whereNumber('staff')->name('edit');
            Route::put('{staff}', 'update')->whereNumber('staff')->name('update');
            Route::patch('{staff}/active', 'toggle')->whereNumber('staff')->name('toggle');
            Route::post('{staff}/resend', 'resend')->whereNumber('staff')->middleware('throttle:10,1')->name('resend');
            Route::delete('{staff}', 'destroy')->whereNumber('staff')->name('destroy');
        });
    });

    Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function (): void {
        Route::middleware('permission:settings.roles.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{role}/edit', 'edit')->whereNumber('role')->name('edit');
        });
        Route::middleware('permission:settings.roles.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::put('{role}', 'update')->whereNumber('role')->name('update');
            Route::delete('{role}', 'destroy')->whereNumber('role')->name('destroy');
        });
    });
});

Route::middleware(['auth:vendor', 'permission:dashboard.view,vendor'])->prefix('settings/account')->controller(AccountController::class)->group(function (): void {
    Route::get('/', 'edit')->name('settings.account');
    Route::put('/', 'update')->middleware('throttle:20,1')->name('settings.account.update');
    Route::put('password', 'password')->middleware('throttle:10,1')->name('settings.account.password');
});

Route::middleware(['auth:vendor', 'permission:settings.audit.view,vendor'])->prefix('settings/audit')->controller(AuditLogController::class)->group(function (): void {
    Route::get('/', 'index')->name('settings.audit');
    Route::get('data', 'data')->name('settings.audit.data');
});
