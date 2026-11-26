<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Http\Requests\Admin\DashboardRequest;
use App\Modules\Platform\Services\AdminDashboardService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, AdminDashboardService $dashboard, CountryService $countries): View
    {
        return view('platform::admin.dashboard', [
            'overview' => $dashboard->overview($request->period(), $request->user('admin')),
            'admin' => $request->user('admin'),
            'countries' => $countries->options(),
        ]);
    }
}
