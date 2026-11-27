<?php

use App\Modules\Storefront\Http\Controllers\Vendor\PageBuilderController;
use App\Modules\Storefront\Services\Layouts\StoreLayoutService;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — link with vendor_route():
|   vendor.store.page-builder          home builder (VEN-032), ?scope=default|<country id>
|   vendor.store.page-builder.page     section builder of one store page (VEN-033 pages)
|   vendor.store.page-builder.*        {target} = home-default | home-<country id> | page-<page id>:
|                                      draft save / discard, publish, reset to theme default,
|                                      versions + restore, signed draft preview, product
|                                      search (Select2), image upload;
|                                      permissions store.design.view | store.design.manage
*/

Route::middleware('auth:vendor')->prefix('store/page-builder')->controller(PageBuilderController::class)->group(function (): void {
    Route::middleware('permission:store.design.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('store.page-builder');
        Route::get('pages/{page}', 'page')->whereNumber('page')->name('store.page-builder.page');
        Route::get('{target}/versions', 'versions')->where('target', StoreLayoutService::TARGET_PATTERN)->name('store.page-builder.versions');
        Route::get('{target}/preview', 'preview')->where('target', StoreLayoutService::TARGET_PATTERN)->name('store.page-builder.preview');
    });

    Route::middleware('permission:store.design.manage,vendor')->name('store.page-builder.')->group(function (): void {
        Route::get('products', 'products')->name('products');
        Route::post('images', 'image')->name('images');
        Route::put('{target}/draft', 'saveDraft')->where('target', StoreLayoutService::TARGET_PATTERN)->name('draft');
        Route::delete('{target}/draft', 'discard')->where('target', StoreLayoutService::TARGET_PATTERN)->name('discard');
        Route::post('{target}/publish', 'publish')->where('target', StoreLayoutService::TARGET_PATTERN)->name('publish');
        Route::post('{target}/reset', 'reset')->where('target', StoreLayoutService::TARGET_PATTERN)->name('reset');
        Route::post('{target}/versions/{version}/restore', 'restore')->where('target', StoreLayoutService::TARGET_PATTERN)->whereNumber('version')->name('restore');
    });
});
