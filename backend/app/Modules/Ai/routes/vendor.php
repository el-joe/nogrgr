<?php

use App\Modules\Ai\Http\Controllers\Vendor\AdStudioController;
use App\Modules\Ai\Http\Controllers\Vendor\AiLogoController;
use App\Modules\Ai\Http\Controllers\Vendor\AiTranslationController;
use App\Modules\Ai\Http\Controllers\Vendor\ProductAiToolsController;
use App\Modules\Ai\Http\Controllers\Vendor\ProductAiTranslationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel — AI tools of the store. VEN-012b "Translate with AI" of one
| product (custom provider, decision #44 a): names
| "vendor.catalog.products.ai-translate.preview|apply" (imported products,
| catalog.products.manage) and "vendor.catalog.own-products.ai-translate.preview|apply"
| (own products, catalog.own-products.manage). Provider calls are throttled.
| VEN-074 / 074a "AI translation" settings page: vendor.settings.ai-translation*
| (settings.languages.view: page, status, history | settings.languages.manage: buy a
| language, payment result / cancel, run again, retry).
| VEN-010b AI price finder + VEN-010c AI social posts of one product (OpenAI only,
| plan `ai_calls`): vendor.catalog.products.ai-price|ai-social (catalog.products.manage)
| and vendor.catalog.own-products.ai-price|ai-social (catalog.own-products.manage).
| VEN-034a AI logo generator: vendor.store.appearance.ai-logo.generate|apply
| (store.appearance.manage, plan `ai_logos`).
| VEN-010c AI Advertising Studio (4-step video ad wizard, plan `ai_ads`):
| vendor.marketing.ads.index|data|show|download (marketing.ads.view) and
| vendor.marketing.ads.products|store|retry|destroy (marketing.ads.manage).
*/

Route::middleware('auth:vendor')->prefix('marketing/ads')->name('marketing.ads.')->controller(AdStudioController::class)->group(function (): void {
    Route::middleware('permission:marketing.ads.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('{ad}', 'show')->whereNumber('ad')->name('show');
        Route::get('{ad}/download', 'download')->whereNumber('ad')->name('download');
    });
    Route::middleware('permission:marketing.ads.manage,vendor')->group(function (): void {
        Route::get('products', 'products')->name('products');
        Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
        Route::post('{ad}/retry', 'retry')->whereNumber('ad')->middleware('throttle:6,1')->name('retry');
        Route::delete('{ad}', 'destroy')->whereNumber('ad')->name('destroy');
    });
});

Route::middleware(['auth:vendor', 'permission:store.appearance.manage,vendor'])->prefix('store/appearance/ai-logo')->name('store.appearance.ai-logo.')->controller(AiLogoController::class)->group(function (): void {
    Route::post('generate', 'generate')->middleware('throttle:5,1')->name('generate');
    Route::post('apply', 'apply')->name('apply');
});

Route::middleware('auth:vendor')->prefix('settings/ai-translation')->controller(AiTranslationController::class)->group(function (): void {
    Route::middleware('permission:settings.languages.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('settings.ai-translation');
        Route::get('status', 'status')->name('settings.ai-translation.status');
        Route::get('history', 'history')->name('settings.ai-translation.history');
    });
    Route::middleware('permission:settings.languages.manage,vendor')->name('settings.ai-translation.')->group(function (): void {
        Route::post('purchase', 'purchase')->middleware('throttle:20,1')->name('purchase');
        Route::get('payments/{payment}/result', 'paymentResult')->whereNumber('payment')->name('payment.result');
        Route::get('payments/{payment}/cancel', 'paymentCancel')->whereNumber('payment')->name('payment.cancel');
        Route::post('runs/{run}/run-again', 'runAgain')->whereNumber('run')->middleware('throttle:10,1')->name('run-again');
        Route::post('runs/{run}/retry', 'retry')->whereNumber('run')->middleware('throttle:10,1')->name('retry');
    });
});

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

    Route::controller(ProductAiToolsController::class)->group(function (): void {
        Route::middleware('permission:catalog.products.manage,vendor')->prefix('products/{product}')->name('products.')->whereNumber('product')->group(function (): void {
            Route::post('ai-price', 'priceFinder')->middleware('throttle:10,1')->name('ai-price');
            Route::post('ai-social', 'socialPost')->middleware('throttle:20,1')->name('ai-social');
        });

        Route::middleware('permission:catalog.own-products.manage,vendor')->prefix('own-products/{product}')->name('own-products.')->whereNumber('product')->group(function (): void {
            Route::post('ai-price', 'priceFinder')->middleware('throttle:10,1')->name('ai-price');
            Route::post('ai-social', 'socialPost')->middleware('throttle:20,1')->name('ai-social');
        });
    });
});
