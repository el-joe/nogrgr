<?php

use App\Modules\Support\Http\Controllers\Storefront\CustomerTicketPageController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — theme account › Support (names "storefront.support.*"), signed-in
| customers only; the forms post to the Store API support endpoints.
*/

Route::middleware('auth:customer')->prefix('account/support')->name('support.')->controller(CustomerTicketPageController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{ticket}', 'show')->whereNumber('ticket')->name('show');
});
