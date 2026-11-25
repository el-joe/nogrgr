<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('content::central.home');
    }
}
