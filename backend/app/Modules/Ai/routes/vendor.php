<?php

use App\Modules\Ai\Http\Controllers\Vendor\ProductAiTranslationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel — AI tools of the store. VEN-012b "Translate with AI" of one
| product (custom provider, decision #44 a): names
| "vendor.catalog.products.ai-translate.preview|apply" (imported products,
| catalog.products.manage) and "vendor.catalog.own-products.ai-translate.preview|apply"
| (own products, catalog.own-products.manage). Provider calls are throttled.
*/

Route::middleware('auth:vendor')->prefix('catalog')->name('catalog.')->group(function (): void {
    Route::controller(ProductAiTranslationController::class)->group(function (): void {
        Route::middleware('permission:catalog.products.manage,vendor')->prefix('products/{product}/ai-translate')->name('products.ai-translate.')->whereNumber('product')->group(function (): void {
            Route::post('preview', 'preview')->middleware('throttle:20,1')->name('preview');
            Route::post('apply', 'apply')->name('apply');
        });

        Route::middleware('permission:catalog.own-products.manage,vendor')->prefix('own-products/{product}/ai-translate')->name('own-products.ai-translate.')->whereNumber('product')->group(function (): void {
            Route::post('preview', 'preview')->middleware('throttle:20,1')->name('preview');
            Route::post('apply', 'apply')->name('apply');
        });
    });
});
