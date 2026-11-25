<?php

use App\Modules\Api\Http\Controllers\Api\PingController;
use Illuminate\Support\Facades\Route;

/*
| Surface: api — /api/mobile/v1, names "api.mobile.v1.*", tenant from the X-Tenant header.
| Authenticated customer routes use auth:sanctum.
*/

Route::get('ping', PingController::class)->name('ping');
