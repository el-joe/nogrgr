<?php

use App\Modules\Billing\Http\Controllers\Owner\OwnerInvoiceController;
use App\Modules\Billing\Http\Controllers\Owner\OwnerSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — Billing ({central}/my-account, names "owner.*").
| Every route: auth:owner + can:owner.portal; each record is checked against the owner
| (gates owner.subscriptions.manage / owner.invoices.view).
*/

Route::middleware(['auth:owner', 'can:owner.portal'])->group(function (): void {
    // Subscriptions (OWN-003)
    Route::prefix('subscriptions')->name('subscriptions.')->controller(OwnerSubscriptionController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::post('{subscription}/renew', 'renew')->whereNumber('subscription')->middleware('throttle:10,1')->name('renew');
        Route::get('{subscription}/payments/{payment}/return', 'paymentReturn')->whereNumber(['subscription', 'payment'])->middleware('throttle:30,1')->name('payment.return');
        Route::get('{subscription}/payments/{payment}/cancel', 'paymentCancel')->whereNumber(['subscription', 'payment'])->middleware('throttle:30,1')->name('payment.cancel');
    });

    // Invoices (OWN-005)
    Route::prefix('invoices')->name('invoices.')->controller(OwnerInvoiceController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{invoice}', 'show')->whereNumber('invoice')->name('show');
        Route::get('{invoice}/print', 'print')->whereNumber('invoice')->name('print');
    });
});
