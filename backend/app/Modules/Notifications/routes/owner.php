<?php

use App\Modules\Notifications\Http\Controllers\Owner\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — {central}/my-account/notifications, names "owner.notifications.*"
| (OWN-008; auth:owner + owner.portal). Every action is scoped to the owner's own rows.
*/

Route::middleware(['auth:owner', 'can:owner.portal'])
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
