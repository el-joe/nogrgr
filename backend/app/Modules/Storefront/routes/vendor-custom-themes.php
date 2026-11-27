<?php

use App\Modules\Storefront\Http\Controllers\Vendor\CustomThemeController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel — Your store › Custom theme (VEN-036 / 036a / 036b, decision #121):
|   vendor.store.custom-themes.*   {version} = custom theme version id of this store;
|                                  permissions store.custom-themes.view | .manage
*/

Route::middleware('auth:vendor')->prefix('store/custom-theme')->name('store.custom-themes.')->controller(CustomThemeController::class)->group(function (): void {
    Route::middleware('permission:store.custom-themes.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('starter-kit', 'starterKit')->middleware('throttle:20,1')->name('starter-kit');
        Route::get('docs', 'docs')->name('docs');
        Route::get('settings', 'settings')->name('settings');
        Route::get('versions/{version}', 'show')->whereNumber('version')->name('show');
        Route::get('versions/{version}/preview', 'preview')->whereNumber('version')->name('preview');
    });
    Route::middleware('permission:store.custom-themes.manage,vendor')->group(function (): void {
        Route::post('/', 'upload')->middleware('throttle:10,1')->name('upload');
        Route::patch('active', 'active')->name('active');
        Route::put('settings', 'updateSettings')->name('settings.update');
        Route::post('versions/{version}/submit', 'submit')->whereNumber('version')->name('submit');
        Route::post('versions/{version}/withdraw', 'withdraw')->whereNumber('version')->name('withdraw');
        Route::post('versions/{version}/publish', 'publish')->whereNumber('version')->name('publish');
        Route::delete('versions/{version}', 'destroy')->whereNumber('version')->name('destroy');
    });
});
