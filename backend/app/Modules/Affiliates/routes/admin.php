<?php

use App\Modules\Affiliates\Http\Controllers\Admin\AffiliateController;
use App\Modules\Affiliates\Http\Controllers\Admin\ConversionController;
use App\Modules\Affiliates\Http\Controllers\Admin\PayoutRequestController;
use App\Modules\Affiliates\Http\Controllers\Admin\ProgramSettingsController;
use App\Modules\Affiliates\Http\Controllers\Admin\PromoCodeController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Affiliate program (ADM-060 / 060a-c, 061, 062, 063).
| Every route: auth:admin + permission (tests/Feature/Architecture/PanelRoutesTest.php).
*/

Route::middleware('auth:admin')->prefix('affiliates')->name('affiliates.')->group(function (): void {
    // Program settings (ADM-060c)
    Route::middleware('permission:affiliates.settings.manage,admin')->controller(ProgramSettingsController::class)->group(function (): void {
        Route::get('settings', 'edit')->name('settings.edit');
        Route::put('settings', 'update')->name('settings.update');
    });

    // Conversions (ADM-061)
    Route::prefix('conversions')->name('conversions.')->controller(ConversionController::class)->group(function (): void {
        Route::middleware('permission:affiliates.conversions.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{conversion}', 'show')->whereNumber('conversion')->name('show');
        });
        Route::middleware('permission:affiliates.conversions.manage,admin')->group(function (): void {
            Route::get('{conversion}/reverse', 'reverseForm')->whereNumber('conversion')->name('reverse.form');
            Route::post('{conversion}/reverse', 'reverse')->whereNumber('conversion')->name('reverse');
            Route::post('{conversion}/reject', 'reject')->whereNumber('conversion')->name('reject');
        });
    });

    // Payout requests + payout history (ADM-062)
    Route::prefix('payouts')->name('payouts.')->controller(PayoutRequestController::class)->group(function (): void {
        Route::middleware('permission:affiliates.payouts.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('history', 'history')->name('history');
            Route::get('{payoutRequest}', 'show')->whereNumber('payoutRequest')->name('show');
            Route::get('paid/{payout}/attachment', 'attachment')->whereNumber('payout')->name('attachment');
        });
        Route::middleware('permission:affiliates.payouts.manage,admin')->group(function (): void {
            Route::post('{payoutRequest}/pay', 'pay')->whereNumber('payoutRequest')->name('pay');
            Route::get('{payoutRequest}/reject', 'rejectForm')->whereNumber('payoutRequest')->name('reject.form');
            Route::post('{payoutRequest}/reject', 'reject')->whereNumber('payoutRequest')->name('reject');
        });
    });

    // Promo codes (ADM-063)
    Route::prefix('promo-codes')->name('promo-codes.')->controller(PromoCodeController::class)->group(function (): void {
        Route::middleware('permission:affiliates.promo-codes.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });
        Route::middleware('permission:affiliates.promo-codes.manage,admin')->group(function (): void {
            Route::get('affiliates', 'affiliates')->name('affiliates');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{promoCode}/edit', 'edit')->whereNumber('promoCode')->name('edit');
            Route::put('{promoCode}', 'update')->whereNumber('promoCode')->name('update');
            Route::delete('{promoCode}', 'destroy')->whereNumber('promoCode')->name('destroy');
        });
    });

    // Accounts (ADM-060 / 060a / 060b)
    Route::controller(AffiliateController::class)->group(function (): void {
        Route::middleware('permission:affiliates.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{affiliate}', 'show')->whereNumber('affiliate')->name('show');
        });
        Route::middleware('permission:affiliates.manage,admin')->group(function (): void {
            Route::get('{affiliate}/edit', 'edit')->whereNumber('affiliate')->name('edit');
            Route::put('{affiliate}', 'update')->whereNumber('affiliate')->name('update');
        });
        Route::middleware('permission:affiliates.payouts.manage,admin')->group(function (): void {
            Route::get('{affiliate}/payout', 'payoutForm')->whereNumber('affiliate')->name('payout.create');
            Route::post('{affiliate}/payout', 'payout')->whereNumber('affiliate')->name('payout.store');
        });
    });
});
