<?php

use App\Modules\Storefront\Http\Controllers\Admin\CustomThemeReviewController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/storefront/theme-reviews (ADM-089 / 089a, decision #121):
| custom Twig theme review queue + detail; permissions storefront.theme-reviews.view | .manage.
*/

Route::middleware('auth:admin')->prefix('storefront/theme-reviews')->name('storefront.theme-reviews.')->controller(CustomThemeReviewController::class)->group(function (): void {
    Route::middleware('permission:storefront.theme-reviews.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{version}', 'show')->whereNumber('version')->name('show');
        Route::get('{version}/preview', 'preview')->whereNumber('version')->name('preview');
        Route::get('{version}/files', 'file')->whereNumber('version')->name('file');
    });
    Route::middleware('permission:storefront.theme-reviews.manage,admin')->group(function (): void {
        Route::post('{version}/decision', 'decide')->whereNumber('version')->name('decide');
    });
});
