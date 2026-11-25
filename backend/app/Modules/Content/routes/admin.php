<?php

use App\Modules\Content\Http\Controllers\Admin\BlogCategoryController;
use App\Modules\Content\Http\Controllers\Admin\BlogPostController;
use App\Modules\Content\Http\Controllers\Admin\FaqController;
use App\Modules\Content\Http\Controllers\Admin\NewsletterController;
use App\Modules\Content\Http\Controllers\Admin\PageController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Website content (ADM-080 … 084): blog categories / posts, static
| pages, FAQs, newsletter subscribers. Every route: auth:admin + permission.
*/

Route::middleware('auth:admin')->prefix('content')->name('content.')->group(function (): void {
    // Blog categories (ADM-080 / 080a)
    Route::prefix('post-categories')->name('post-categories.')->controller(BlogCategoryController::class)->group(function (): void {
        Route::middleware('permission:content.post-categories.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:content.post-categories.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{category}/edit', 'edit')->whereNumber('category')->name('edit');
            Route::put('{category}', 'update')->whereNumber('category')->name('update');
            Route::delete('{category}', 'destroy')->whereNumber('category')->name('destroy');
        });
    });

    // Blog posts (ADM-081 / 081a)
    Route::prefix('posts')->name('posts.')->controller(BlogPostController::class)->group(function (): void {
        Route::middleware('permission:content.posts.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:content.posts.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{post}/edit', 'edit')->whereNumber('post')->name('edit');
            Route::put('{post}', 'update')->whereNumber('post')->name('update');
            Route::post('{post}/publish', 'publish')->whereNumber('post')->name('publish');
            Route::post('{post}/archive', 'archive')->whereNumber('post')->name('archive');
            Route::delete('{post}', 'destroy')->whereNumber('post')->name('destroy');
        });
    });

    // Static pages (ADM-082 / 082a)
    Route::prefix('pages')->name('pages.')->controller(PageController::class)->group(function (): void {
        Route::middleware('permission:content.pages.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::middleware('permission:content.pages.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{page}/edit', 'edit')->whereNumber('page')->name('edit');
            Route::put('{page}', 'update')->whereNumber('page')->name('update');
            Route::post('{page}/activate', 'activate')->whereNumber('page')->name('activate');
            Route::post('{page}/archive', 'archive')->whereNumber('page')->name('archive');
            Route::delete('{page}', 'destroy')->whereNumber('page')->name('destroy');
        });
    });

    // FAQs (ADM-083 / 083a)
    Route::prefix('faqs')->name('faqs.')->controller(FaqController::class)->group(function (): void {
        Route::get('/', 'index')->middleware('permission:content.faqs.view,admin')->name('index');

        Route::middleware('permission:content.faqs.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('sort', 'sort')->name('sort');
            Route::get('{faq}/edit', 'edit')->whereNumber('faq')->name('edit');
            Route::put('{faq}', 'update')->whereNumber('faq')->name('update');
            Route::delete('{faq}', 'destroy')->whereNumber('faq')->name('destroy');
        });
    });

    // Newsletter subscribers (ADM-084)
    Route::prefix('newsletter')->name('newsletter.')->controller(NewsletterController::class)->group(function (): void {
        Route::middleware('permission:content.newsletter.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
        });

        Route::get('export', 'export')->middleware('permission:content.newsletter.export,admin')->name('export');
        Route::delete('{subscriber}', 'destroy')->whereNumber('subscriber')->middleware('permission:content.newsletter.manage,admin')->name('destroy');
    });
});
