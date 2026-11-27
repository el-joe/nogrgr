<?php

use App\Modules\Storefront\Http\Controllers\Vendor\BannerController;
use App\Modules\Storefront\Http\Controllers\Vendor\ThemeController;
use App\Modules\Storefront\Services\Themes\ThemePageService;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.store.banners.*   store banners (VEN-035 / 035a), {banner} = store banner id
|                            (visibility: central banner id); permissions
|                            store.banners.view | store.banners.manage
*/

Route::middleware('auth:vendor')->prefix('store/banners')->name('store.banners.')->controller(BannerController::class)->group(function (): void {
    Route::middleware('permission:store.banners.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::middleware('permission:store.banners.manage,vendor')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('sort', 'sort')->name('sort');
        Route::patch('defaults', 'defaults')->name('defaults');
        Route::get('{banner}/edit', 'edit')->whereNumber('banner')->name('edit');
        Route::put('{banner}', 'update')->whereNumber('banner')->name('update');
        Route::patch('{banner}/active', 'toggle')->whereNumber('banner')->name('toggle');
        Route::delete('{banner}', 'destroy')->whereNumber('banner')->name('destroy');
        Route::patch('central/{banner}/visibility', 'visibility')->whereNumber('banner')->name('visibility');
    });
});

/*
|   vendor.store.themes.*        store themes (VEN-030 / 030a / 030b, decision #119): {theme} =
|                                catalogue theme id, {scope} = "default" | selling country id;
|   vendor.store.home-variants   Themes by country (VEN-031, route name kept for old links);
|                                permissions store.themes.view | store.themes.manage
*/

Route::middleware('auth:vendor')->controller(ThemeController::class)->group(function (): void {
    Route::middleware('permission:store.themes.view,vendor')->group(function (): void {
        Route::get('store/themes', 'index')->name('store.themes.index');
        Route::get('store/themes/{theme}/preview', 'preview')->whereNumber('theme')->name('store.themes.preview');
        Route::get('store/home-variants', 'byCountry')->name('store.home-variants');
    });
    Route::middleware('permission:store.themes.manage,vendor')->prefix('store/themes')->name('store.themes.')->group(function (): void {
        Route::post('activate', 'activate')->name('activate');
        Route::get('{theme}/countries', 'countries')->whereNumber('theme')->name('countries');
        Route::get('scope/{scope}', 'scope')->where('scope', ThemePageService::SCOPE_PATTERN)->name('scope');
        Route::put('scope/{scope}', 'updateScope')->where('scope', ThemePageService::SCOPE_PATTERN)->name('scope.update');
        Route::delete('countries/{scope}', 'reset')->whereNumber('scope')->name('reset');
        Route::delete('selection', 'resetPlatform')->name('reset-platform');
    });
});
