<?php

use App\Modules\Api\Http\Controllers\Admin\StoreMobileAppController;
use Illuminate\Support\Facades\Route;

/*
| Surface: admin — Vendors › profile › Mobile app tab save (ADM-041g), vendors.manage.
| The tab is the vendor profile page (?tab=mobile-app).
*/

Route::middleware(['auth:admin', 'permission:vendors.manage,admin'])
    ->put('vendors/{tenant}/mobile-app', [StoreMobileAppController::class, 'update'])
    ->whereNumber('tenant')
    ->middleware('throttle:30,1')
    ->name('vendors.mobile-app.update');
