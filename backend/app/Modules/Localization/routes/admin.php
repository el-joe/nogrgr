<?php

use App\Modules\Localization\Http\Controllers\Admin\CountryController;
use App\Modules\Localization\Http\Controllers\Admin\CurrencyController;
use App\Modules\Localization\Http\Controllers\Admin\LanguageController;
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
});
