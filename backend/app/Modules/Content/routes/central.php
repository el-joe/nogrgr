<?php

use App\Modules\Content\Http\Controllers\Central\BlogController;
use App\Modules\Content\Http\Controllers\Central\ContactController;
use App\Modules\Content\Http\Controllers\Central\HomeController;
use App\Modules\Content\Http\Controllers\Central\LocaleController;
use App\Modules\Content\Http\Controllers\Central\NewsletterController;
use App\Modules\Content\Http\Controllers\Central\PageController;
use App\Modules\Content\Http\Controllers\Central\PricingController;
use App\Modules\Content\Http\Controllers\Central\SeoController;
use App\Modules\Content\Http\Controllers\Central\TemplateController;
use Illuminate\Support\Facades\Route;

/*
| Surface: central — {central}/, names "central.*". Public marketing website (P6,
| WEB-001 … 015, 025). Theme preview (WEB-004) arrives in P7.
*/

Route::get('/', HomeController::class)->name('home');
Route::get('templates', [TemplateController::class, 'index'])->name('templates');
Route::get('templates/category/{slug}', [TemplateController::class, 'category'])->where('slug', '[a-z0-9-]+')->name('templates.category');
Route::get('pricing', PricingController::class)->name('pricing');
Route::get('how-it-works', [PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('about', [PageController::class, 'about'])->name('about');
Route::get('faqs', [PageController::class, 'faqs'])->name('faqs');
Route::get('terms', [PageController::class, 'terms'])->name('terms');
Route::get('privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('pages/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('page');

Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:5,10')->name('contact.store');

Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('blog.show');

Route::post('newsletter', [NewsletterController::class, 'store'])->middleware('throttle:5,1')->name('newsletter.store');
Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('newsletter.confirm');
Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('newsletter.unsubscribe');

Route::get('locale/{locale}', LocaleController::class)->where('locale', '[a-z]{2}')->name('locale');

Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
