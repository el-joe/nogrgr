<?php

use App\Modules\Platform\Http\Controllers\Admin\ComplianceController;
use App\Modules\Platform\Http\Controllers\Admin\ComplianceDocumentController;
use Illuminate\Support\Facades\Route;

/*
| Admin › Vendors › Compliance / KYC (ADM-044 / 044a): review queue with status tabs,
| store review page, private document streaming (vendors.kyc.view), decisions and
| per-document flags (vendors.kyc.manage).
*/

Route::middleware('auth:admin')->prefix('vendors/compliance')->name('vendors.compliance.')->group(function (): void {
    Route::middleware('permission:vendors.kyc.view,admin')->group(function (): void {
        Route::get('/', [ComplianceController::class, 'index'])->name('index');
        Route::get('data', [ComplianceController::class, 'data'])->name('data');
        Route::get('{complianceProfile}', [ComplianceController::class, 'show'])->whereNumber('complianceProfile')->name('show');
        Route::get('{complianceProfile}/documents/{document}', [ComplianceDocumentController::class, 'show'])
            ->whereNumber(['complianceProfile', 'document'])
            ->name('document');
    });

    Route::middleware('permission:vendors.kyc.manage,admin')->group(function (): void {
        Route::post('{complianceProfile}/decision', [ComplianceController::class, 'decide'])->whereNumber('complianceProfile')->name('decide');
        Route::patch('{complianceProfile}/documents/{document}', [ComplianceDocumentController::class, 'status'])
            ->whereNumber(['complianceProfile', 'document'])
            ->name('document.status');
    });
});
