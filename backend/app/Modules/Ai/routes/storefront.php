<?php

use App\Modules\Ai\Http\Controllers\Storefront\ImageSearchController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — search by image (SF-ECO-012 / SF-ELO-013 / SF-SQF-013), JSON for
| the header camera modal. Public; metered by the store plan's monthly image searches.
*/

Route::post('search/image', ImageSearchController::class)->middleware('throttle:10,1')->name('search.image');
