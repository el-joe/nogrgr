<?php

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Controllers\Vendor\EditRequestController;
use App\Modules\Catalog\Http\Controllers\Vendor\NewProductController;
use App\Modules\Catalog\Http\Controllers\Vendor\OpportunityController;
use App\Modules\Catalog\Http\Controllers\Vendor\OwnProductController;
use App\Modules\Catalog\Http\Controllers\Vendor\PriceListController;
use App\Modules\Catalog\Http\Controllers\Vendor\ProductSortController;
use App\Modules\Catalog\Http\Controllers\Vendor\Requests\RequestAttachmentController;
use App\Modules\Catalog\Http\Controllers\Vendor\Requests\RequestPaymentController;
use App\Modules\Catalog\Http\Controllers\Vendor\Requests\VendorRequestController;
use App\Modules\Catalog\Http\Controllers\Vendor\StoreProductController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — store catalog, names "vendor.catalog.*"
| (link with vendor_route('catalog.…')). Imported central products need
| `catalog.products.view|manage|export`, own products `catalog.own-products.view|manage`.
| Ids are tenant `store_products.id`, except {central} (central `products.id`).
*/

Route::middleware('auth:vendor')->prefix('catalog')->name('catalog.')->group(function (): void {
    Route::prefix('products')->name('products.')->group(function (): void {
        Route::middleware('permission:catalog.products.view,vendor')->group(function (): void {
            Route::controller(StoreProductController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('data', 'data')->name('data');
                Route::get('options', 'options')->name('options');
                Route::get('{product}/edit', 'edit')->whereNumber('product')->name('edit');
                Route::get('{product}/shipping', 'shipping')->whereNumber('product')->name('shipping');
            });

            Route::get('{product}/price-list', [PriceListController::class, 'show'])->whereNumber('product')->name('price-list');

            Route::get('new', [NewProductController::class, 'index'])->name('new');

            Route::controller(NewProductController::class)->prefix('new')->name('new.')->group(function (): void {
                Route::get('data', 'data')->name('data');
                Route::get('{central}', 'show')->whereNumber('central')->name('show');
            });

            Route::get('sort', [ProductSortController::class, 'index'])->name('sort');
        });

        Route::get('export', [StoreProductController::class, 'export'])->middleware('permission:catalog.products.export,vendor')->name('export');

        Route::middleware('permission:catalog.products.manage,vendor')->group(function (): void {
            Route::controller(StoreProductController::class)->group(function (): void {
                Route::put('{product}', 'update')->whereNumber('product')->name('update');
                Route::patch('{product}/active', 'toggle')->whereNumber('product')->name('active');
                Route::patch('{product}/featured', 'toggle')->whereNumber('product')->name('featured');
                Route::delete('{product}', 'destroy')->whereNumber('product')->name('destroy');
                Route::post('{product}/pricing/preview', 'pricingPreview')->whereNumber('product')->name('pricing.preview');
            });

            Route::controller(PriceListController::class)->group(function (): void {
                Route::post('{product}/price-list/preview', 'preview')->whereNumber('product')->name('price-list.preview');
                Route::post('price-list', 'store')->name('price-list.store');
                Route::get('price-list/runs/{run}', 'run')->whereUuid('run')->name('price-list.run');
            });

            Route::post('new/import', [NewProductController::class, 'import'])->middleware('throttle:30,1')->name('new.import');
            Route::post('sort', [ProductSortController::class, 'update'])->name('sort.update');
        });
    });

    Route::controller(EditRequestController::class)->prefix('edit-requests')->name('edit-requests.')->group(function (): void {
        Route::middleware('permission:catalog.products.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('form', 'form')->name('form');
            Route::get('{editRequest}', 'show')->whereNumber('editRequest')->name('show');
        });

        Route::post('/', 'store')->middleware(['permission:catalog.products.manage,vendor', 'throttle:20,1'])->name('store');
    });

    Route::controller(OpportunityController::class)->prefix('opportunities')->name('opportunities.')->group(function (): void {
        Route::middleware('permission:catalog.products.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{central}/markets', 'markets')->whereNumber('central')->name('markets');
        });

        Route::post('{central}/markets', 'saveMarkets')->whereNumber('central')->middleware('permission:catalog.products.manage,vendor')->name('markets.store');
    });

    Route::controller(OwnProductController::class)->prefix('own-products')->name('own-products.')->group(function (): void {
        Route::middleware('permission:catalog.own-products.view,vendor')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('sections/{section}', 'section')->name('show');
            Route::get('inventory', 'inventory')->name('inventory');
        });

        Route::middleware('permission:catalog.own-products.manage,vendor')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{product}/edit', 'edit')->whereNumber('product')->name('edit');
            Route::put('{product}', 'update')->whereNumber('product')->name('update');
            Route::patch('{product}/active', 'toggle')->whereNumber('product')->name('active');
            Route::patch('{product}/stock', 'stock')->whereNumber('product')->name('stock');
            Route::delete('{product}', 'destroy')->whereNumber('product')->name('destroy');
        });
    });
});

/*
| Surface: vendor panel — Requests (VEN-050 … 050j), names "vendor.requests.*": the store's
| own product / manufacturing / brand requests ("requests.<kind>.*", the kind is read from
| the route name by RequestKind::fromVendorRouteName(); ids are
| central request ids limited to the current store). Permissions per kind on the vendor
| guard: requests.products|manufacturing|brands.view / .manage.
*/

Route::middleware('auth:vendor')->prefix('requests')->name('requests.')->group(function (): void {
    // Any request view permission; the kind-specific check happens in the policy / controller.
    Route::middleware('permission:'.implode('|', array_map(static fn (RequestKind $kind): string => $kind->permission('view'), RequestKind::cases())).',vendor')->group(function (): void {
        Route::get('/', [VendorRequestController::class, 'hub'])->name('index');
        Route::get('attachments/{attachment}', RequestAttachmentController::class)->whereNumber('attachment')->name('attachments.show');
        Route::post('broadcasting/auth', [VendorRequestController::class, 'broadcastAuth'])->middleware('throttle:60,1')->withoutMiddleware('audit.requests')->name('broadcast-auth');

        Route::controller(RequestPaymentController::class)->prefix('payments/{payment}')->name('payment.')->whereNumber('payment')->group(function (): void {
            Route::get('result', 'result')->name('result');
            Route::get('cancel', 'cancel')->name('cancel');
        });
    });

    foreach (RequestKind::cases() as $kind) {
        Route::prefix($kind->value)->name($kind->value.'.')->group(function () use ($kind): void {
            Route::middleware('permission:'.$kind->permission('view').',vendor')->controller(VendorRequestController::class)->group(function () use ($kind): void {
                Route::get('/', 'index')->name('index');
                Route::get('data', 'data')->name('data');
                Route::get('{id}', 'show')->whereNumber('id')->name('show');
                Route::get('{id}/messages', 'messages')->whereNumber('id')->name('messages');

                if ($kind === RequestKind::Manufacturing) {
                    Route::get('export', 'export')->name('export');
                }
            });

            Route::middleware('permission:'.$kind->permission('manage').',vendor')->group(function () use ($kind): void {
                Route::controller(VendorRequestController::class)->group(function () use ($kind): void {
                    Route::get('create', 'create')->name('create');
                    Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
                    Route::post('{id}/reply', 'reply')->whereNumber('id')->middleware('throttle:60,1')->name('reply');

                    if ($kind === RequestKind::Manufacturing) {
                        Route::get('products', 'products')->name('products');
                        Route::post('{id}/cancel', 'cancel')->whereNumber('id')->name('cancel');
                    }

                    if ($kind === RequestKind::Brand) {
                        Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
                    }
                });

                if ($kind->hasPayments()) {
                    Route::post('{id}/payments/{payment}/pay', [RequestPaymentController::class, 'pay'])->whereNumber(['id', 'payment'])->middleware('throttle:20,1')->name('payments.pay');
                }
            });
        });
    }
});
