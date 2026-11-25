<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function home(): RedirectResponse
    {
        return redirect()->to(vendor_route('dashboard'));
    }

    public function show(): View
    {
        return view('platform::vendor.dashboard', ['tenantName' => tenant('name')]);
    }
}
