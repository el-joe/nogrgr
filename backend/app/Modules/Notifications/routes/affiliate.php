<?php

use App\Modules\Notifications\Http\Controllers\Affiliate\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: affiliate — {central}/affiliate/notifications, names "affiliate.notifications.*"
| (AFF-014). Every affiliate with `notifications.view` manages their own inbox.
*/

Route::middleware(['auth:affiliate', 'permission:notifications.view,affiliate'])
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
        Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
    });
