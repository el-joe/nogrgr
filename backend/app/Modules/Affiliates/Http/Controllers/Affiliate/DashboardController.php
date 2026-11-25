<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('affiliates::dashboard');
    }
}
