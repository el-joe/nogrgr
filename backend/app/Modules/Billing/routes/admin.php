<?php

use App\Modules\Billing\Http\Controllers\Admin\InvoiceController;
use App\Modules\Billing\Http\Controllers\Admin\PaymentLogController;
use App\Modules\Billing\Http\Controllers\Admin\PlanController;
use App\Modules\Billing\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Billing (plans, subscriptions, invoices, payment logs).
| Every route: auth:admin + permission (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware('auth:admin')->group(function (): void {
    // Plans (ADM-046 / 046a)
    Route::middleware('permission:plans.view,admin')->group(function (): void {
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('plans/data', [PlanController::class, 'data'])->name('plans.data');
    });

    Route::middleware('permission:plans.manage,admin')->group(function (): void {
        Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->whereNumber('plan')->name('plans.edit');
        Route::put('plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->name('plans.update');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->whereNumber('plan')->name('plans.destroy');
    });

    // Subscriptions (ADM-047)
    Route::prefix('billing/subscriptions')->name('billing.subscriptions.')->controller(SubscriptionController::class)->group(function (): void {
        Route::middleware('permission:subscriptions.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::get('export', 'export')->middleware('permission:subscriptions.export,admin')->name('export');

        Route::middleware('permission:subscriptions.manage,admin')->group(function (): void {
            Route::get('tenants', 'tenants')->name('tenants');
            Route::post('assign', 'assign')->name('assign');
            Route::post('{subscription}/renew', 'renew')->whereNumber('subscription')->name('renew');
            Route::post('{subscription}/cancel', 'cancel')->whereNumber('subscription')->name('cancel');
        });
    });

    // Invoices (ADM-054 / 054a)
    Route::prefix('finance/invoices')->name('finance.invoices.')->controller(InvoiceController::class)
        ->middleware('permission:finance.invoices.view,admin')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{invoice}', 'show')->whereNumber('invoice')->name('show');
            Route::get('{invoice}/print', 'print')->whereNumber('invoice')->name('print');
        });

    // Payment logs (ADM-055 / 055a)
    Route::prefix('finance/payment-logs')->name('finance.payment-logs.')->controller(PaymentLogController::class)->group(function (): void {
        Route::middleware('permission:finance.payment-logs.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{paymentLog}', 'show')->whereNumber('paymentLog')->name('show');
        });

        Route::patch('{paymentLog}/status', 'updateStatus')
            ->whereNumber('paymentLog')
            ->middleware('permission:finance.payment-logs.manage,admin')
            ->name('status');
    });
});
