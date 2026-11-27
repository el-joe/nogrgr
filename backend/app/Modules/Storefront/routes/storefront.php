<?php

use App\Modules\Storefront\Http\Controllers\Storefront\CartController;
use App\Modules\Storefront\Http\Controllers\Storefront\CategoryController;
use App\Modules\Storefront\Http\Controllers\Storefront\CheckoutController;
use App\Modules\Storefront\Http\Controllers\Storefront\HomeController;
use App\Modules\Storefront\Http\Controllers\Storefront\ListingController;
use App\Modules\Storefront\Http\Controllers\Storefront\PageController;
use App\Modules\Storefront\Http\Controllers\Storefront\PaymentReturnController;
use App\Modules\Storefront\Http\Controllers\Storefront\PreferencesController;
use App\Modules\Storefront\Http\Controllers\Storefront\ProductController;
use App\Modules\Storefront\Http\Controllers\Storefront\RobotsController;
use App\Modules\Storefront\Http\Controllers\Storefront\SearchController;
use App\Modules\Storefront\Http\Controllers\Storefront\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — {store-domain}/, names "storefront.*", tenant DB. Public.
| Theme views resolve through Storefront\Support\StorefrontView (family → Ecommet fallback).
*/

Route::get('/', HomeController::class)->name('home');
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::post('preferences', PreferencesController::class)->middleware('throttle:30,1')->name('preferences');

// Catalog (sf-catalog): listings answer `?fragment=1` with the next page of cards (JSON).
Route::get('home/tab/{tab}', [HomeController::class, 'tab'])->where('tab', '[a-z0-9][a-z0-9-]{0,190}')->name('home.tab');
Route::get('best-selling', [ListingController::class, 'bestSelling'])->name('best-sellers');
Route::get('new-in', [ListingController::class, 'newIn'])->name('new-in');
Route::get('top-rated', [ListingController::class, 'topRated'])->name('top-rated');
Route::get('offers', [ListingController::class, 'offers'])->name('offers');
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('category', [CategoryController::class, 'all'])->name('category.all');
Route::get('category/{slug}', [CategoryController::class, 'show'])->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]{0,190}')->name('category');
Route::get('products/{product}/{slug?}', ProductController::class)->whereNumber('product')->name('product');
Route::get('search', [SearchController::class, 'index'])->name('search');
Route::get('search/autocomplete', [SearchController::class, 'autocomplete'])->middleware('throttle:120,1')->name('search.autocomplete');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

// SF-ECO-034 store CMS pages (sf-data)
Route::get('pages/{slug}', PageController::class)->where('slug', '[a-z0-9][a-z0-9-]{0,119}')->name('pages.show');

// Customer account pages: orders, returns, wishlist (sf-account)
require __DIR__.'/storefront/account.php';

// Cart & checkout (sf-checkout): pages + server-rendered fragments; mutations go through /store-api/v1.
Route::get('cart', [CartController::class, 'index'])->name('cart');
Route::get('cart/fragment', [CartController::class, 'fragment'])->middleware('throttle:120,1')->name('cart.fragment');
Route::get('cart/mini', [CartController::class, 'mini'])->middleware('throttle:120,1')->name('cart.mini');
Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::get('checkout/fragment', [CheckoutController::class, 'fragment'])->middleware('throttle:120,1')->name('checkout.fragment');
Route::get('checkout/pay/{order}', [CheckoutController::class, 'pay'])->whereUuid('order')->name('checkout.pay');
Route::get('checkout/payment/{gateway}/{order}', PaymentReturnController::class)->where('gateway', '[a-z0-9_]+')->whereUuid('order')->name('checkout.payment.return');
