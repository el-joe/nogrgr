<?php

use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Http\Controllers\Admin\AttributeController;
use App\Modules\Catalog\Http\Controllers\Admin\BadgeController;
use App\Modules\Catalog\Http\Controllers\Admin\CategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\CategorySortController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductImportController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductLabelController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductSortController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/catalog, names "admin.catalog.*".
| `catalog.<resource>.view` reads; `catalog.<resource>.manage` (implies view) changes.
*/

Route::middleware('auth:admin')->prefix('catalog')->name('catalog.')->group(function (): void {
    Route::prefix('products')->name('products.')->group(function (): void {
        Route::controller(ProductController::class)->group(function (): void {
            Route::middleware('permission:catalog.products.view,admin')->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('data', 'data')->name('data');
                Route::get('{product}/prices', 'prices')->whereNumber('product')->name('prices');
                Route::get('{product}/prices/export', 'pricesExport')->whereNumber('product')->middleware('permission:catalog.products.export,admin')->name('prices.export');
            });

            Route::get('export', 'export')->middleware('permission:catalog.products.export,admin')->name('export');

            Route::middleware('permission:catalog.products.manage,admin')->group(function (): void {
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::post('bulk', 'bulk')->name('bulk');
                Route::post('validate/{product?}', 'validateField')->whereNumber('product')->name('validate');
                Route::get('tenants', 'tenants')->name('tenants');
                Route::get('{product}/edit', 'edit')->whereNumber('product')->name('edit');
                Route::put('{product}', 'update')->whereNumber('product')->name('update');
                Route::delete('{product}', 'destroy')->whereNumber('product')->name('destroy');
                Route::patch('{product}/restore', 'restore')->whereNumber('product')->name('restore');
            });
        });

        Route::controller(ProductSortController::class)->prefix('sort')->middleware('permission:catalog.products.manage,admin')->group(function (): void {
            Route::get('/', 'index')->name('sort');
            Route::get('data', 'data')->name('sort.data');
            Route::put('/', 'save')->name('sort.save');
            Route::post('reset', 'reset')->name('sort.reset');
        });
    });

    Route::prefix('categories')->name('categories.')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:catalog.categories.view,admin')->name('index');

        Route::middleware('permission:catalog.categories.manage,admin')->group(function (): void {
            Route::controller(CategoryController::class)->group(function (): void {
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::put('tree', 'saveTree')->name('tree');
                Route::get('{category}/edit', 'edit')->whereNumber('category')->name('edit');
                Route::put('{category}', 'update')->whereNumber('category')->name('update');
                Route::patch('{category}/featured', 'featured')->whereNumber('category')->name('featured');
                Route::delete('{category}', 'destroy')->whereNumber('category')->name('destroy');
            });

            Route::controller(CategorySortController::class)->group(function (): void {
                Route::get('sort', 'categories')->name('sort');
                Route::get('{category}/products', 'products')->whereNumber('category')->name('products');
                Route::put('{category}/products/order', 'saveProducts')->whereNumber('category')->name('products.order');
            });
        });
    });

    Route::controller(AttributeController::class)->prefix('attributes')->name('attributes.')->group(function (): void {
        Route::middleware('permission:catalog.attributes.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:catalog.attributes.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{attribute}/edit', 'edit')->whereNumber('attribute')->name('edit');
            Route::put('{attribute}', 'update')->whereNumber('attribute')->name('update');
            Route::delete('{attribute}', 'destroy')->whereNumber('attribute')->name('destroy');
        });
    });

    Route::controller(ProductLabelController::class)->prefix('labels')->name('labels.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:catalog.labels.view,admin')->name('index');

        Route::middleware('permission:catalog.labels.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::put('order', 'sort')->name('sort');
            Route::get('{label}/edit', 'edit')->whereNumber('label')->name('edit');
            Route::put('{label}', 'update')->whereNumber('label')->name('update');
            Route::patch('{label}/active', 'toggle')->whereNumber('label')->name('toggle');
            Route::delete('{label}', 'destroy')->whereNumber('label')->name('destroy');
        });
    });

    Route::controller(BadgeController::class)->prefix('badges')->name('badges.')->group(function (): void {
        $types = BadgeType::values();

        Route::middleware('permission:catalog.badges.view,admin')->group(function () use ($types): void {
            Route::get('/', 'index')->name('index');
            Route::get('{type}', 'show')->whereIn('type', $types)->name('show');
        });

        Route::middleware('permission:catalog.badges.manage,admin')->group(function () use ($types): void {
            Route::put('{type}', 'update')->whereIn('type', $types)->name('update');
            Route::get('{type}/search', 'search')->whereIn('type', $types)->name('search');
            Route::post('{type}/products', 'assign')->whereIn('type', $types)->name('assign');
            Route::put('{type}/order', 'sort')->whereIn('type', $types)->name('sort');
            Route::delete('{type}/products/{product}', 'remove')->whereIn('type', $types)->whereNumber('product')->name('remove');
        });
    });
});

/*
| Catalog › Products › Import (ADM-013): {central}/admin/catalog/imports, names
| "admin.catalog.imports.*". `catalog.imports.view` reads runs / logs; `.manage` runs,
| previews (calls the external API, throttled), pauses / cancels and maps categories.
*/
Route::middleware('auth:admin')->prefix('catalog/imports')->name('catalog.imports.')->controller(ProductImportController::class)->group(function (): void {
    Route::middleware('permission:catalog.imports.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('runs', 'runs')->name('runs');
        Route::get('runs/{run}/progress', 'progress')->whereNumber('run')->name('progress');
        Route::get('runs/{run}/logs', 'logs')->whereNumber('run')->name('logs');
        Route::get('categories', 'categories')->name('categories');
    });

    Route::middleware('permission:catalog.imports.manage,admin')->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('preview', 'preview')->middleware('throttle:12,1')->name('preview');
        Route::patch('runs/{run}/pause', 'pause')->whereNumber('run')->name('pause');
        Route::patch('runs/{run}/resume', 'resume')->whereNumber('run')->name('resume');
        Route::patch('runs/{run}/cancel', 'cancel')->whereNumber('run')->name('cancel');
        Route::put('categories', 'saveCategories')->name('categories.save');
    });
});

require __DIR__.'/admin/requests.php';
