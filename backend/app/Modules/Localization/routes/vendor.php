<?php

use App\Modules\Localization\Http\Controllers\Vendor\CurrencySettingsController;
use App\Modules\Localization\Http\Controllers\Vendor\StoreLanguageController;
use App\Modules\Localization\Http\Controllers\Vendor\StoreTranslationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.settings.currencies*    shopper currencies (VEN-072), display only (USD is charged);
|                                  settings.currencies.view | manage
|   vendor.settings.languages.*    store languages, buy a language, purchases (VEN-073/a/b);
|                                  settings.languages.view | manage
|   vendor.settings.translations*  static texts + store content per language (VEN-075);
|                                  settings.translations.view | manage
*/

Route::middleware('auth:vendor')->prefix('settings/currencies')->controller(CurrencySettingsController::class)->group(function (): void {
    Route::get('/', 'index')->middleware('permission:settings.currencies.view,vendor')->name('settings.currencies');
    Route::put('/', 'update')->middleware(['permission:settings.currencies.manage,vendor', 'throttle:30,1'])->name('settings.currencies.update');
});

Route::middleware('auth:vendor')->prefix('settings/languages')->name('settings.languages.')->controller(StoreLanguageController::class)->group(function (): void {
    Route::middleware('permission:settings.languages.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('history', 'history')->name('history');
        Route::get('runs', 'runs')->name('runs');
    });
    Route::middleware('permission:settings.languages.manage,vendor')->group(function (): void {
        Route::put('/', 'update')->middleware('throttle:30,1')->name('update');
        Route::post('{language}/purchase', 'purchase')->whereNumber('language')->middleware('throttle:20,1')->name('purchase');
        Route::get('payments/{payment}/result', 'paymentResult')->whereNumber('payment')->name('payment.result');
        Route::get('payments/{payment}/cancel', 'paymentCancel')->whereNumber('payment')->name('payment.cancel');
        Route::post('{code}/retry', 'retry')->where('code', '[a-z]{2,3}(-[a-z0-9]{2,8})?')->middleware('throttle:10,1')->name('retry');
    });
});

Route::middleware('auth:vendor')->prefix('settings/translations')->controller(StoreTranslationController::class)->group(function (): void {
    Route::middleware('permission:settings.translations.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('settings.translations');
        Route::get('data', 'data')->name('settings.translations.data');
        Route::get('content', 'content')->name('settings.translations.content');
        Route::get('content/data', 'contentData')->name('settings.translations.content.data');
    });
    Route::middleware('permission:settings.translations.manage,vendor')->name('settings.translations.')->group(function (): void {
        Route::put('static', 'updateStatic')->middleware('throttle:60,1')->name('static.update');
        Route::post('static/reset', 'resetStatic')->middleware('throttle:60,1')->name('static.reset');
        Route::post('static/suggest', 'suggestStatic')->middleware('throttle:30,1')->name('static.suggest');
        Route::post('static/translate-missing', 'translateMissing')->middleware('throttle:5,1')->name('static.translate-missing');
        Route::put('content', 'updateContent')->middleware('throttle:60,1')->name('content.update');
        Route::post('content/unlock', 'unlockContent')->middleware('throttle:60,1')->name('content.unlock');
        Route::post('content/suggest', 'suggestContent')->middleware('throttle:30,1')->name('content.suggest');
    });
});
