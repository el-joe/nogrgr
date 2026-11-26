<?php

use App\Modules\Billing\Http\Controllers\Vendor\InvoiceController;
use App\Modules\Billing\Http\Controllers\Vendor\MoneyFlowController;
use App\Modules\Billing\Http\Controllers\Vendor\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — money flow of the current store (decision #72):
|   vendor.finance.purchases.*    purchases owed to the platform (VEN-062)
|   vendor.finance.settlements.*  settlement payments vendor → platform (VEN-063)
|   vendor.finance.payouts.*      payouts received platform → store (VEN-064)
|   vendor.finance.subscription*  subscription & balance, renew / change plan / pay invoice (VEN-060…060d)
|   vendor.finance.invoices.*     platform invoices list / detail / print-PDF (VEN-061 / 061a); detail and
|                                 print also open with finance.subscription.view (links from VEN-060c)
*/

Route::middleware('auth:vendor')->prefix('finance/invoices')->name('finance.invoices.')->controller(InvoiceController::class)->group(function (): void {
    Route::middleware('permission:finance.invoices.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::middleware('permission:finance.invoices.view|finance.subscription.view,vendor')->group(function (): void {
        Route::get('{invoice}', 'show')->whereNumber('invoice')->name('show');
        Route::get('{invoice}/print', 'print')->whereNumber('invoice')->name('print');
    });
});

Route::middleware('auth:vendor')->prefix('finance')->name('finance.')->controller(MoneyFlowController::class)->group(function (): void {
    Route::middleware('permission:finance.purchases.view,vendor')->group(function (): void {
        Route::get('purchases', 'purchases')->name('purchases.index');
        Route::get('purchases/data', 'purchasesData')->name('purchases.data');
        Route::get('purchases/export', 'purchasesExport')->name('purchases.export');
    });

    Route::middleware('permission:finance.settlements.view,vendor')->group(function (): void {
        Route::get('settlements', 'settlements')->name('settlements.index');
        Route::get('settlements/data', 'settlementsData')->name('settlements.data');
        Route::get('settlements/{reference}', 'settlement')->where('reference', 'ST-[A-Za-z0-9-]+')->name('settlements.show');
    });

    Route::middleware('permission:finance.payouts.view,vendor')->group(function (): void {
        Route::get('payouts', 'payouts')->name('payouts.index');
        Route::get('payouts/data', 'payoutsData')->name('payouts.data');
    });
});

Route::middleware('auth:vendor')->prefix('finance/subscription')->controller(SubscriptionController::class)->group(function (): void {
    Route::middleware('permission:finance.subscription.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('finance.subscription');
        Route::get('history/data', 'historyData')->name('finance.subscription.history.data');
        Route::get('invoices/data', 'invoicesData')->name('finance.subscription.invoices.data');
        Route::get('invoices/{invoice}/print', 'printInvoice')->whereNumber('invoice')->name('finance.subscription.invoices.print');
        Route::get('transactions/data', 'transactionsData')->name('finance.subscription.transactions.data');
    });

    Route::middleware('permission:finance.subscription.manage,vendor')->group(function (): void {
        Route::post('checkout', 'checkout')->middleware('throttle:10,1')->name('finance.subscription.checkout');
        Route::get('payments/{payment}/result', 'result')->whereNumber('payment')->middleware('throttle:30,1')->name('finance.subscription.result');
        Route::get('payments/{payment}/cancel', 'cancel')->whereNumber('payment')->middleware('throttle:30,1')->name('finance.subscription.cancel');
    });
});
