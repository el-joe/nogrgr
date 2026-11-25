<?php

use App\Modules\Localization\Http\Controllers\Admin\CountryController;
use App\Modules\Localization\Http\Controllers\Admin\CurrencyController;
use App\Modules\Localization\Http\Controllers\Admin\LanguageController;
use App\Modules\Localization\Http\Controllers\Admin\LanguagePurchaseController;
use App\Modules\Localization\Http\Controllers\Admin\TranslationController;
use App\Modules\Localization\Http\Controllers\Admin\TranslationKeyLockController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/localization, names "admin.localization.*".
| `*.view` reads the lists; `*.manage` (implies view) changes them.
*/

Route::middleware('auth:admin')->prefix('localization')->name('localization.')->group(function (): void {
    Route::controller(LanguageController::class)->prefix('languages')->name('languages.')->group(function (): void {
        Route::middleware('permission:localization.languages.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:localization.languages.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{language}/edit', 'edit')->whereNumber('language')->name('edit');
            Route::put('{language}', 'update')->whereNumber('language')->name('update');
            Route::patch('{language}/active', 'toggle')->whereNumber('language')->name('toggle');
            Route::delete('{language}', 'destroy')->whereNumber('language')->name('destroy');
        });
    });

    Route::controller(CurrencyController::class)->prefix('currencies')->name('currencies.')->group(function (): void {
        Route::middleware('permission:localization.currencies.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:localization.currencies.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('sync', 'sync')->middleware('throttle:6,1')->name('sync');
            Route::get('{currency}/edit', 'edit')->whereNumber('currency')->name('edit');
            Route::put('{currency}', 'update')->whereNumber('currency')->name('update');
            Route::patch('{currency}/active', 'toggle')->whereNumber('currency')->name('toggle');
            Route::delete('{currency}', 'destroy')->whereNumber('currency')->name('destroy');
        });
    });

    Route::controller(CountryController::class)->prefix('countries')->name('countries.')->group(function (): void {
        Route::middleware('permission:localization.countries.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:localization.countries.manage,admin')->group(function (): void {
            Route::get('{country}/edit', 'edit')->whereNumber('country')->name('edit');
            Route::put('{country}', 'update')->whereNumber('country')->name('update');
            Route::patch('{country}/active', 'toggle')->whereNumber('country')->name('toggle');
        });
    });

    /* Translations editor (ADM-092): keys are passed as ?file=&key= (they contain dots). */
    Route::controller(TranslationController::class)->prefix('translations')->name('translations.')->group(function (): void {
        Route::middleware('permission:localization.translations.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('entry', 'show')->name('show');
            Route::get('export', 'export')->middleware('throttle:30,1')->name('export');
        });

        Route::middleware('permission:localization.translations.manage,admin')->group(function (): void {
            Route::get('entry/edit', 'edit')->name('edit');
            Route::put('entry', 'update')->name('update');
            Route::delete('entry', 'revert')->name('revert');
            Route::get('keys/create', 'create')->name('create');
            Route::post('keys', 'store')->name('store');
            Route::get('files/create', 'createFile')->name('files.create');
            Route::post('files', 'storeFile')->name('files.store');
        });
    });

    /* Translation key locks (ADM-093). */
    Route::controller(TranslationKeyLockController::class)->prefix('key-locks')->name('key-locks.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:localization.key-locks.view,admin')->name('index');
        Route::patch('/', 'update')->middleware('permission:localization.key-locks.manage,admin')->name('update');
    });

    /* Language & AI translation purchases (ADM-094), read from Billing. */
    Route::controller(LanguagePurchaseController::class)->prefix('purchases')->name('purchases.')
        ->middleware('permission:localization.purchases.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{purchase}', 'show')->whereNumber('purchase')->name('show');
        });
});
