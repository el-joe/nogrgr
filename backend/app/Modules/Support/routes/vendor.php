<?php

use App\Modules\Support\Http\Controllers\Vendor\HelpCenterController;
use App\Modules\Support\Http\Controllers\Vendor\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Surface: vendor panel (both host modes) — Support tickets (VEN-091 / 091a / 091b),
| names "vendor.support.*" (support.view reads, support.manage opens / replies / closes),
| and the Help center (VEN-092 / 092a), names "vendor.help.*" (every staff member).
*/

Route::middleware('auth:vendor')->prefix('support')->name('support.')->controller(TicketController::class)->group(function (): void {
    Route::middleware('permission:support.view,vendor')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('attachments/{attachment}', 'attachment')->whereNumber('attachment')->name('attachments.show');
        Route::post('broadcasting/auth', 'broadcastAuth')->middleware('throttle:60,1')->withoutMiddleware('audit.requests')->name('broadcast-auth');
        Route::get('{ticket}', 'show')->whereNumber('ticket')->name('show');
        Route::get('{ticket}/messages', 'messages')->whereNumber('ticket')->name('messages');
    });

    Route::middleware('permission:support.manage,vendor')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::post('{ticket}/messages', 'reply')->whereNumber('ticket')->middleware('throttle:60,1')->name('reply');
        Route::post('{ticket}/close', 'close')->whereNumber('ticket')->name('close');
        Route::post('{ticket}/reopen', 'reopen')->whereNumber('ticket')->name('reopen');
    });
});

Route::middleware(['auth:vendor', 'permission:dashboard.view,vendor'])->prefix('help')->name('help.')->controller(HelpCenterController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('articles/{slug}', 'article')->where('slug', '[a-z0-9-]+')->name('article');
    Route::post('articles/{slug}/feedback', 'feedback')->where('slug', '[a-z0-9-]+')->middleware('throttle:30,1')->name('feedback');
});
