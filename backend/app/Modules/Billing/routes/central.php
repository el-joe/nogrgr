<?php

use App\Modules\Billing\Http\Controllers\Central\PaymentWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
| Gateway webhooks (names "central.webhooks.*"): no session state, CSRF-exempt, throttled;
| every request is signature-checked by the gateway driver.
*/

Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '[a-z]+')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');
