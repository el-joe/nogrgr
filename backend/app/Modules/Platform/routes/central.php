<?php

use App\Modules\Platform\Http\Controllers\Central\CompleteRegistrationController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationAvailabilityController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationPaymentController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationPlanController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationProvisioningController;
use App\Modules\Platform\Http\Controllers\Central\RegistrationVerifyController;
use App\Modules\Platform\Http\Controllers\Central\SignUpController;
use Illuminate\Support\Facades\Route;

/*
| Central sign-up wizard (WEB-016 / 016b / 016c / 017 / 019 / 019a / 019b): sign up →
| plan → payment (paid plans) → e-mail verification. State lives in the session; the
| gateway return pages are signed URLs bound to the registration + payment log.
*/

Route::get('register', [SignUpController::class, 'show'])->name('register');
Route::post('register', [SignUpController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

Route::prefix('register')->name('register.')->group(function (): void {
    Route::get('social', [SignUpController::class, 'social'])->name('social');
    Route::get('plan', [RegistrationPlanController::class, 'show'])->name('plan');
    Route::post('plan', [RegistrationPlanController::class, 'store'])->middleware('throttle:20,1')->name('plan.store');

    Route::get('payment', [RegistrationPaymentController::class, 'show'])->name('payment');
    Route::post('payment', [RegistrationPaymentController::class, 'store'])->middleware('throttle:10,1')->name('payment.store');
    Route::get('payment/{registration}/{payment}/charge', [RegistrationPaymentController::class, 'charge'])->whereNumber(['registration', 'payment'])->name('payment.charge');
    Route::get('payment/{registration}/{payment}/success', [RegistrationPaymentController::class, 'success'])->whereNumber(['registration', 'payment'])->middleware('throttle:30,1')->name('payment.success');
    Route::get('payment/{registration}/{payment}/cancel', [RegistrationPaymentController::class, 'cancel'])->whereNumber(['registration', 'payment'])->middleware('throttle:30,1')->name('payment.cancel');

    Route::get('verify', [RegistrationVerifyController::class, 'show'])->name('verify');
    Route::post('verify/resend', [RegistrationVerifyController::class, 'resend'])->middleware('throttle:6,1')->name('verify.resend');
    Route::post('verify/email', [RegistrationVerifyController::class, 'changeEmail'])->middleware('throttle:6,1')->name('verify.email');
    Route::get('continue', [RegistrationVerifyController::class, 'continue'])->name('continue');
});

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
