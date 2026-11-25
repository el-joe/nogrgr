<?php

namespace App\Modules\Storefront\Http\Controllers\Storefront;

use App\Modules\Storefront\Support\StorefrontTheme;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('storefront::home', [
            'storeName' => StorefrontTheme::storeName(),
            'family' => StorefrontTheme::family(),
        ]);
    }
}
