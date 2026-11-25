<?php

use App\Modules\Platform\Http\Controllers\Admin\PendingRegistrationController;
use Illuminate\Support\Facades\Route;

/*
| Admin › Vendors › Pending registrations (ADM-042): sign-ups that have not finished
| payment or store setup. List + detail (vendors.view), resend the completion link
| (vendors.manage, throttled) and delete (vendors.delete).
*/

Route::middleware('auth:admin')->prefix('vendors/pending')->name('vendors.pending')->controller(PendingRegistrationController::class)->group(function (): void {
    Route::middleware('permission:vendors.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('');
        Route::get('data', 'data')->name('.data');
        Route::get('{registration}', 'show')->whereNumber('registration')->name('.show');
    });

    Route::post('{registration}/resend', 'resend')->whereNumber('registration')->middleware(['permission:vendors.manage,admin', 'throttle:10,1'])->name('.resend');
    Route::delete('{registration}', 'destroy')->whereNumber('registration')->middleware('permission:vendors.delete,admin')->name('.destroy');
});
