<?php

use App\Modules\Storefront\Http\Controllers\Storefront\HomeController;
use App\Modules\Storefront\Http\Controllers\Storefront\RobotsController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — {store-domain}/, names "storefront.*", tenant DB. Public.
*/

Route::get('/', HomeController::class)->name('home');
Route::get('robots.txt', RobotsController::class)->name('robots');
