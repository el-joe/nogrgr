<?php

use App\Modules\Ai\Http\Controllers\Admin\AdminTranslationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/localization/ai-translation, names
| "admin.localization.ai-translation.*" (P10, custom provider, decision #44 a):
| catalogue run (ADM-091b, localization.languages.manage), static keys modal / run /
| one-key suggestion (ADM-092a, localization.translations.manage) and the progress of a
| run (policy: view = the kind's .view permission, retry / stop = its .manage).
*/

Route::middleware('auth:admin')->prefix('localization/ai-translation')->name('localization.ai-translation.')
    ->controller(AdminTranslationController::class)->group(function (): void {
        Route::post('catalog', 'catalog')->middleware(['permission:localization.languages.manage,admin', 'throttle:10,1'])->name('catalog');

        Route::middleware('permission:localization.translations.manage,admin')->prefix('static-keys')->name('static-keys.')->group(function (): void {
            Route::get('create', 'staticKeysModal')->name('create');
            Route::post('/', 'staticKeys')->middleware('throttle:10,1')->name('store');
            Route::post('suggest', 'suggest')->middleware('throttle:30,1')->name('suggest');
        });

        Route::prefix('runs/{run}')->whereNumber('run')->name('runs.')->group(function (): void {
            Route::get('/', 'show')->middleware('can:view,run')->name('show');
            Route::post('retry', 'retry')->middleware(['can:manage,run', 'throttle:10,1'])->name('retry');
            Route::post('stop', 'stop')->middleware('can:manage,run')->name('stop');
        });
    });
