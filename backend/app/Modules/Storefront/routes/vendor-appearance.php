<?php

use App\Modules\Storefront\Http\Controllers\Vendor\AppearanceController;
use App\Modules\Storefront\Http\Controllers\Vendor\MenuLinkController;
use App\Modules\Storefront\Http\Controllers\Vendor\SocialLinkController;
use App\Modules\Storefront\Http\Controllers\Vendor\StorePageController;
use App\Modules\Storefront\Http\Controllers\Vendor\TrackingController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.store.pages.*         store CMS pages (VEN-033 / 033a), {page} = store_pages id;
|                                store.pages.view | store.pages.manage
|   vendor.store.appearance*     appearance tabs (VEN-034b…f): logo, colours & fonts, promo
|                                bar, header / footer links ({link} = store_menu_links id),
|                                social links ({link} = store_social_links id);
|                                store.appearance.view | store.appearance.manage
|   vendor.settings.tracking*    tracking pixels (VEN-083); settings.tracking.view | manage
*/

Route::middleware('auth:vendor')->prefix('store/pages')->name('store.pages.')->controller(StorePageController::class)->group(function (): void {
    Route::middleware('permission:store.pages.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
    });
    Route::middleware('permission:store.pages.manage,vendor')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{page}/edit', 'edit')->whereNumber('page')->name('edit');
        Route::put('{page}', 'update')->whereNumber('page')->name('update');
        Route::patch('{page}/flag', 'toggle')->whereNumber('page')->name('toggle');
        Route::delete('{page}', 'destroy')->whereNumber('page')->name('destroy');
    });
});

Route::middleware('auth:vendor')->prefix('store/appearance')->group(function (): void {
    Route::get('/', [AppearanceController::class, 'index'])->middleware('permission:store.appearance.view,vendor')->name('store.appearance');

    Route::middleware('permission:store.appearance.manage,vendor')->name('store.appearance.')->group(function (): void {
        Route::controller(AppearanceController::class)->group(function (): void {
            Route::post('logo', 'logo')->name('logo');
            Route::put('colors', 'colors')->name('colors');
            Route::delete('colors', 'resetColors')->name('colors.reset');
            Route::put('promo', 'promo')->name('promo');
            Route::put('footer', 'footer')->name('footer');
        });

        Route::prefix('links')->name('links.')->controller(MenuLinkController::class)->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('sort', 'sort')->name('sort');
            Route::get('{link}/edit', 'edit')->whereNumber('link')->name('edit');
            Route::put('{link}', 'update')->whereNumber('link')->name('update');
            Route::patch('{link}/active', 'toggle')->whereNumber('link')->name('toggle');
            Route::delete('{link}', 'destroy')->whereNumber('link')->name('destroy');
        });

        Route::prefix('social')->name('social.')->controller(SocialLinkController::class)->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{link}/edit', 'edit')->whereNumber('link')->name('edit');
            Route::put('{link}', 'update')->whereNumber('link')->name('update');
            Route::patch('{link}/active', 'toggle')->whereNumber('link')->name('toggle');
            Route::delete('{link}', 'destroy')->whereNumber('link')->name('destroy');
        });
    });
});

Route::middleware('auth:vendor')->prefix('settings/tracking')->controller(TrackingController::class)->group(function (): void {
    Route::get('/', 'index')->middleware('permission:settings.tracking.view,vendor')->name('settings.tracking');
    Route::put('/', 'update')->middleware('permission:settings.tracking.manage,vendor')->name('settings.tracking.update');
});
