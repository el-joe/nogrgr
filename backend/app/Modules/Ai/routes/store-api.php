<?php

use App\Modules\Ai\Http\Controllers\Storefront\ImageSearchController;
use Illuminate\Support\Facades\Route;

/*
| Surface: store-api — /store-api/v1, names "store-api.v1.*". Search by image for the
| mobile app (multipart `image` or `url`), same JSON as the storefront modal.
*/

Route::post('search/image', ImageSearchController::class)->middleware('throttle:10,1')->name('search.image');
