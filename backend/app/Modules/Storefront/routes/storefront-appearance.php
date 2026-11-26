<?php

use App\Modules\Storefront\Http\Controllers\Storefront\AppearanceStylesheetController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — the store's appearance tokens stylesheet (VEN-034b/c, decision #91),
| linked from x-sf::layout with a content-hash ?v=.
*/

Route::get('appearance/{family}.css', AppearanceStylesheetController::class)->where('family', '[a-z]{3,20}')->name('appearance.css');
