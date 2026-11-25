<?php

use App\Modules\Platform\Http\Controllers\Admin\VendorController;
use App\Modules\Platform\Http\Controllers\Admin\VendorCreateController;
use App\Modules\Platform\Http\Controllers\Admin\VendorImpersonationController;
use App\Modules\Platform\Http\Controllers\Admin\VendorProfileController;
use App\Modules\Platform\Http\Controllers\Admin\VendorStatusController;
use Illuminate\Support\Facades\Route;

/*
| Admin › Vendors › All vendors (ADM-040), Create vendor (ADM-040a) and the vendor
| profile (ADM-041…041i). Literal paths vendors/pending, vendors/compliance and
| vendors/domains belong to the registrations / compliance / domains pages.
*/

Route::middleware('auth:admin')->prefix('vendors')->name('vendors.')->group(function (): void {
    Route::middleware('permission:vendors.view,admin')->group(function (): void {
        Route::get('/', [VendorController::class, 'index'])->name('index');
        Route::get('data', [VendorController::class, 'data'])->name('data');
        Route::get('{tenant}', [VendorProfileController::class, 'show'])->whereNumber('tenant')->name('show');
    });

    Route::get('export', [VendorController::class, 'export'])->middleware(['permission:vendors.export,admin', 'throttle:10,1'])->name('export');

    Route::middleware('permission:vendors.manage,admin')->group(function (): void {
        Route::get('create', [VendorCreateController::class, 'create'])->name('create');
        Route::post('/', [VendorCreateController::class, 'store'])->name('store');
        Route::get('slug-check', [VendorCreateController::class, 'slugCheck'])->middleware('throttle:60,1')->name('slug-check');

        Route::put('{tenant}', [VendorProfileController::class, 'update'])->whereNumber('tenant')->name('update');
        Route::put('{tenant}/commission', [VendorProfileController::class, 'commission'])->whereNumber('tenant')->name('commission');

        Route::post('{tenant}/activate', [VendorStatusController::class, 'activate'])->whereNumber('tenant')->name('activate');
        Route::post('{tenant}/suspend', [VendorStatusController::class, 'suspend'])->whereNumber('tenant')->name('suspend');
        Route::post('{tenant}/retry', [VendorStatusController::class, 'retry'])->whereNumber('tenant')->name('retry');
    });

    Route::delete('{tenant}', [VendorStatusController::class, 'destroy'])->whereNumber('tenant')->middleware('permission:vendors.delete,admin')->name('destroy');

    Route::post('{tenant}/impersonate', VendorImpersonationController::class)->whereNumber('tenant')->middleware(['permission:tenants.impersonate,admin', 'throttle:20,1'])->name('impersonate');
});
