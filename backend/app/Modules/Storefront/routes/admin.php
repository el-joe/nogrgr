<?php

use App\Modules\Storefront\Http\Controllers\Admin\BannerController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/storefront/*, names "admin.storefront.*".
| ADM-085 / 085a default banners (sf-data); permissions storefront.banners.view|manage.
*/

Route::middleware('auth:admin')->prefix('storefront/banners')->name('storefront.banners.')->controller(BannerController::class)->group(function (): void {
    Route::middleware('permission:storefront.banners.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::middleware('permission:storefront.banners.manage,admin')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('sort', 'sort')->name('sort');
        Route::get('stores', 'stores')->name('stores');
        Route::get('{banner}/edit', 'edit')->whereNumber('banner')->name('edit');
        Route::put('{banner}', 'update')->whereNumber('banner')->name('update');
        Route::patch('{banner}/active', 'toggle')->whereNumber('banner')->name('toggle');
        Route::delete('{banner}', 'destroy')->whereNumber('banner')->name('destroy');
    });
});
