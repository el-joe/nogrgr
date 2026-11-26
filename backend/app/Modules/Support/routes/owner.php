<?php

use App\Modules\Support\Http\Controllers\Owner\TicketController;
use Illuminate\Support\Facades\Route;

/*
| Surface: owner portal — support tickets of the Help & support page (OWN-009), names
| "owner.support.tickets.*" (auth:owner + owner.portal; each ticket is checked against
| the owner's account and stores by SupportTicketPolicy).
*/

Route::middleware(['auth:owner', 'can:owner.portal'])->prefix('support/tickets')->name('support.tickets.')->controller(TicketController::class)->group(function (): void {
    Route::get('data', 'data')->name('data');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
    Route::get('attachments/{attachment}', 'attachment')->whereNumber('attachment')->name('attachments.show');
    Route::get('{ticket}', 'show')->whereNumber('ticket')->name('show');
    Route::get('{ticket}/messages', 'messages')->whereNumber('ticket')->name('messages');
    Route::post('{ticket}/messages', 'reply')->whereNumber('ticket')->middleware('throttle:60,1')->name('reply');
    Route::post('{ticket}/close', 'close')->whereNumber('ticket')->name('close');
    Route::post('{ticket}/reopen', 'reopen')->whereNumber('ticket')->name('reopen');
});
