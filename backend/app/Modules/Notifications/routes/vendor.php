<?php

use App\Modules\Notifications\Http\Controllers\Vendor\NotificationController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — topbar bell of the store's own inbox,
| names "vendor.notifications.*" (notifications.view). The full inbox page ships with
| the vendor notifications screen; until then the bell has no "View all" link.
*/

Route::middleware(['auth:vendor', 'permission:notifications.view,vendor'])
    ->controller(NotificationController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('latest', 'latest')->name('latest');
        Route::post('read-all', 'markAll')->name('read-all');
        Route::post('broadcasting/auth', 'broadcastAuth')->middleware('throttle:60,1')->withoutMiddleware('audit.requests')->name('broadcast-auth');
        Route::get('{notification}/open', 'open')->whereUuid('notification')->name('open');
    });
