<?php

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
