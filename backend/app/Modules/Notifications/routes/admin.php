<?php

use App\Modules\Notifications\Http\Controllers\Admin\EmailTemplateController;
use App\Modules\Notifications\Http\Controllers\Admin\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — {central}/admin/notifications, names "admin.notifications.*".
| Every admin with `notifications.view` manages their own inbox and preferences.
*/

Route::middleware(['auth:admin', 'permission:notifications.view,admin'])
    ->controller(NotificationController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('latest', 'latest')->name('latest');
        Route::get('preferences', 'preferences')->name('preferences');
        Route::put('preferences', 'updatePreferences')->name('preferences.update');
        Route::post('read-all', 'markAll')->name('read-all');
        Route::post('broadcasting/auth', 'broadcastAuth')->middleware('throttle:60,1')->withoutMiddleware('audit.requests')->name('broadcast-auth');
        Route::get('{notification}/open', 'open')->whereUuid('notification')->name('open');
        Route::patch('{notification}/read', 'mark')->whereUuid('notification')->name('mark');
        Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
    });

/*
| Surface: admin — {central}/admin/settings/email-templates, names
| "admin.settings.email-templates.*" (email-templates.view / .manage).
*/

Route::middleware('auth:admin')
    ->controller(EmailTemplateController::class)
    ->prefix('settings/email-templates')
    ->name('settings.email-templates.')
    ->group(function (): void {
        Route::middleware('permission:email-templates.view,admin')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('data', 'data')->name('data');
            Route::get('{template}/preview', 'preview')->whereNumber('template')->name('preview');
        });

        Route::middleware('permission:email-templates.manage,admin')->group(function (): void {
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('sync', 'syncAll')->middleware('throttle:6,1')->name('sync-all');
            Route::get('{template}/edit', 'edit')->whereNumber('template')->name('edit');
            Route::put('{template}', 'update')->whereNumber('template')->name('update');
            Route::delete('{template}', 'destroy')->whereNumber('template')->name('destroy');
            Route::post('{template}/test', 'test')->whereNumber('template')->middleware('throttle:10,1')->name('test');
            Route::post('{template}/sync', 'sync')->whereNumber('template')->middleware('throttle:12,1')->name('sync');
        });
    });
