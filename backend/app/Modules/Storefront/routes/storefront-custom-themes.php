<?php

use App\Modules\Storefront\Http\Controllers\Storefront\ThemeAssetController;
use Illuminate\Support\Facades\Route;

/*
| Surface: storefront — custom Twig theme assets (decision #121), names "storefront.theme-assets".
*/

Route::get('theme-assets/{version}/{path}', ThemeAssetController::class)
    ->whereNumber('version')
    ->where('path', '[A-Za-z0-9._/-]{1,180}')
    ->name('theme-assets');
