<?php

use App\Modules\Content\Http\Controllers\Central\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Surface: central — {central}/, names "central.*". Public marketing website.
*/

Route::get('/', HomeController::class)->name('home');
