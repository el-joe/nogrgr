<?php

use App\Modules\Notifications\Http\Controllers\Vendor\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — the store's own inbox (VEN-090 page: feed,
| open, mark read / unread, mark all read) and the topbar bell, names
| "vendor.notifications.*" (notifications.view).
*/

Route::middleware(['auth:vendor', 'permission:notifications.view,vendor'])
    ->controller(NotificationController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('latest', 'latest')->name('latest');
        Route::post('read-all', 'markAll')->name('read-all');
        Route::post('broadcasting/auth', 'broadcastAuth')->middleware('throttle:60,1')->withoutMiddleware('audit.requests')->name('broadcast-auth');
        Route::get('{notification}/open', 'open')->whereUuid('notification')->name('open');
        Route::patch('{notification}/read', 'mark')->whereUuid('notification')->name('mark');
    });
