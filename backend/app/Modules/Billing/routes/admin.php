<?php

use App\Modules\Billing\Http\Controllers\Admin\Finance\LedgerController;
use App\Modules\Billing\Http\Controllers\Admin\Finance\PayoutController;
use App\Modules\Billing\Http\Controllers\Admin\Finance\SettlementController;
use App\Modules\Billing\Http\Controllers\Admin\Finance\TransactionController;
use App\Modules\Billing\Http\Controllers\Admin\InvoiceController;
use App\Modules\Billing\Http\Controllers\Admin\PaymentLogController;
use App\Modules\Billing\Http\Controllers\Admin\PlanController;
use App\Modules\Billing\Http\Controllers\Admin\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Billing (plans, subscriptions, invoices, payment logs; finance ledger,
| settlements, payouts, transactions — decision #72).
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

    // Ledger (ADM-050 / 050a)
    Route::prefix('finance/ledger')->name('finance.ledger.')->controller(LedgerController::class)->group(function (): void {
        Route::middleware('permission:finance.ledger.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('export', 'export')->name('export');
            Route::get('{tenant}', 'show')->whereNumber('tenant')->name('show');
            Route::get('{tenant}/entries', 'entries')->whereNumber('tenant')->name('entries');
            Route::get('{tenant}/statement', 'statement')->whereNumber('tenant')->name('statement');
        });
        Route::middleware('permission:finance.ledger.manage,admin')->group(function (): void {
            Route::get('{tenant}/settle', 'settleForm')->whereNumber('tenant')->name('settle.form');
            Route::post('{tenant}/settle', 'settle')->whereNumber('tenant')->name('settle');
        });
    });

    // Settlements vendor → platform (ADM-051)
    Route::prefix('finance/settlements')->name('finance.settlements.')->controller(SettlementController::class)
        ->middleware('permission:finance.settlements.view,admin')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{reference}', 'show')->where('reference', 'ST-[A-Za-z0-9-]+')->name('show');
            Route::get('{reference}/attachment', 'attachment')->where('reference', 'ST-[A-Za-z0-9-]+')->name('attachment');
        });

    // Payouts platform → store (ADM-052 / 052a / 052b)
    Route::prefix('finance/payouts')->name('finance.payouts.')->controller(PayoutController::class)->group(function (): void {
        Route::middleware('permission:finance.payouts.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('{payout}', 'show')->whereNumber('payout')->name('show');
        });
        Route::middleware('permission:finance.payouts.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{payout}/edit', 'edit')->whereNumber('payout')->name('edit');
            Route::put('{payout}', 'update')->whereNumber('payout')->name('update');
            Route::post('{payout}/cancel', 'cancel')->whereNumber('payout')->name('cancel');
        });
    });

    // Transactions (ADM-053)
    Route::prefix('finance/transactions')->name('finance.transactions.')->controller(TransactionController::class)
        ->middleware('permission:finance.transactions.view,admin')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('stores', 'stores')->name('stores');
            Route::get('export', 'export')->name('export');
            Route::get('{transaction}', 'show')->whereNumber('transaction')->name('show');
        });
});
