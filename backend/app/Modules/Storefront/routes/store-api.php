<?php

use App\Modules\Storefront\Http\Controllers\StoreApi\CategoryController;
use App\Modules\Storefront\Http\Controllers\StoreApi\ContentController;
use App\Modules\Storefront\Http\Controllers\StoreApi\HomeController;
use App\Modules\Storefront\Http\Controllers\StoreApi\ProductController;
use App\Modules\Storefront\Http\Controllers\StoreApi\SearchController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.*". Public catalog + content of
| the store (decision #106): the same storefront services as the themes, priced for the
| shopping country (X-Country / X-Country-Code) in USD with the X-Currency display
| conversion. Reference: docs/plan/mobile-api.md.
*/

Route::get('home', [HomeController::class, 'show'])->name('home');
Route::get('home/tabs/{tab}', [HomeController::class, 'tab'])->where('tab', '[a-z0-9][a-z0-9-]{0,190}')->name('home.tab');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{slug}', [CategoryController::class, 'show'])->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]{0,190}')->name('categories.show');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');

Route::get('search', [SearchController::class, 'index'])->name('search');
Route::get('search/autocomplete', [SearchController::class, 'autocomplete'])->middleware('throttle:120,1')->name('search.autocomplete');

Route::get('pages', [ContentController::class, 'pages'])->name('pages.index');
Route::get('pages/{slug}', [ContentController::class, 'page'])->where('slug', '[a-z0-9][a-z0-9-]{0,119}')->name('pages.show');
Route::get('shell', [ContentController::class, 'shell'])->name('shell');
