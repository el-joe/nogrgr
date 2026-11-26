<?php

use App\Modules\Support\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Support tickets (ADM-116 / 116a), names "admin.support.tickets.*".
| support.tickets.view reads (list, detail, polling, files), support.tickets.manage acts
| (reply / internal note, status / priority / assignee, resolve).
*/

Route::middleware('auth:admin')->prefix('support/tickets')->name('support.tickets.')->controller(TicketController::class)->group(function (): void {
    Route::middleware('permission:support.tickets.view,admin')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('data', 'data')->name('data');
        Route::get('stores', 'stores')->name('stores');
        Route::get('attachments/{attachment}', 'attachment')->whereNumber('attachment')->name('attachments.show');
        Route::get('{ticket}', 'show')->whereNumber('ticket')->name('show');
        Route::get('{ticket}/messages', 'messages')->whereNumber('ticket')->name('messages');
    });

    Route::middleware('permission:support.tickets.manage,admin')->group(function (): void {
        Route::post('{ticket}/messages', 'reply')->whereNumber('ticket')->middleware('throttle:30,1')->name('reply');
        Route::patch('{ticket}', 'update')->whereNumber('ticket')->name('update');
        Route::post('{ticket}/resolve', 'resolve')->whereNumber('ticket')->name('resolve');
    });
});
