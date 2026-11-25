<?php

use App\Modules\Platform\Http\Controllers\Central\CompleteRegistrationController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationAvailabilityController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationProvisioningController;
use Illuminate\Support\Facades\Route;

/*
| Central registration completion (WEB-018 / 018a / 020), names prefixed "central.".
| The completion link is verified in the controller so a bad link renders a page, not a 403.
*/

Route::prefix('register')->name('register.')->group(function (): void {
    Route::get('complete', [CompleteRegistrationController::class, 'show'])->name('complete');
    Route::post('complete/account', [CompleteRegistrationController::class, 'account'])->middleware('throttle:20,1')->name('complete.account');
    Route::post('complete/store', [CompleteRegistrationController::class, 'store'])->middleware('throttle:20,1')->name('complete.store');
    Route::get('availability', RegistrationAvailabilityController::class)->middleware('throttle:30,1')->name('availability');
    Route::get('provisioning/{registration}', [RegistrationProvisioningController::class, 'show'])->name('provisioning');
    Route::get('provisioning/{registration}/status', [RegistrationProvisioningController::class, 'status'])->middleware('signed')->name('provisioning.status');
});
