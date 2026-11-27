<?php

use App\Modules\Storefront\Http\Controllers\Admin\ThemeController;
use App\Modules\Storefront\Http\Controllers\Admin\ThemePartController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — names "admin.storefront.*" (decisions #119 / #120):
|   storefront.themes.*          ADM-086 / 086a themes catalogue, {theme} = theme id;
|                                permissions storefront.themes.view | storefront.themes.manage
|   storefront.home-sections.*   ADM-087 home sections per theme (storefront.themes.*)
|   storefront.template-parts.*  ADM-088 / 088a, {part} = theme part id;
|                                permissions storefront.template-parts.view | .manage
*/

Route::middleware('auth:admin')->controller(ThemeController::class)->group(function (): void {
    Route::middleware('permission:storefront.themes.view,admin')->group(function (): void {
        Route::get('storefront/themes', 'index')->name('storefront.themes.index');
        Route::get('storefront/home-sections', 'sections')->name('storefront.home-sections.index');
    });
    Route::middleware('permission:storefront.themes.manage,admin')->group(function (): void {
        Route::prefix('storefront/themes/{theme}')->whereNumber('theme')->name('storefront.themes.')->group(function (): void {
            Route::post('activate', 'activate')->name('activate');
            Route::post('deactivate', 'deactivate')->name('deactivate');
            Route::post('default', 'makeDefault')->name('default');
            Route::get('edit', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
            Route::get('countries', 'countries')->name('countries');
            Route::put('countries', 'updateCountries')->name('countries.update');
        });
        Route::put('storefront/home-sections/{theme}', 'updateSections')->whereNumber('theme')->name('storefront.home-sections.update');
        Route::delete('storefront/home-sections/{theme}', 'resetSections')->whereNumber('theme')->name('storefront.home-sections.reset');
    });
});

Route::middleware('auth:admin')->prefix('storefront/template-parts')->name('storefront.template-parts.')->controller(ThemePartController::class)->group(function (): void {
    Route::middleware('permission:storefront.template-parts.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::middleware('permission:storefront.template-parts.manage,admin')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{part}/edit', 'edit')->whereNumber('part')->name('edit');
        Route::put('{part}', 'update')->whereNumber('part')->name('update');
        Route::patch('{part}/active', 'toggle')->whereNumber('part')->name('toggle');
        Route::post('{part}/default', 'makeDefault')->whereNumber('part')->name('default');
        Route::delete('{part}', 'destroy')->whereNumber('part')->name('destroy');
    });
});
