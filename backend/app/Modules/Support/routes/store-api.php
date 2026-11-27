<?php

use App\Modules\Support\Http\Controllers\StoreApi\CustomerTicketController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.support.tickets.*". Customer ↔
| store support tickets for the app and the theme account pages: signed-in customer only
| (401 JSON), own tickets only (404). Files behind temporary relative signed URLs.
*/

Route::prefix('support/tickets')->name('support.tickets.')->controller(CustomerTicketController::class)->group(function (): void {
    Route::get('options', 'options')->name('options');
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('{ticket}', 'show')->whereNumber('ticket')->name('show');
    Route::post('{ticket}/messages', 'reply')->whereNumber('ticket')->middleware('throttle:30,1')->name('reply');
    Route::post('{ticket}/close', 'close')->whereNumber('ticket')->name('close');
    Route::get('{ticket}/attachments/{attachment}', 'attachment')->whereNumber(['ticket', 'attachment'])->middleware('signed:relative')->name('attachments');
});
