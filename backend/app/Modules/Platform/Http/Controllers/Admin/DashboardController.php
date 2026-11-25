<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\DTOs\DashboardPeriodData;
use App\Modules\Platform\Http\Requests\Admin\DashboardRequest;
use App\Modules\Platform\Services\AdminDashboardService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, AdminDashboardService $dashboard): View
    {
        return view('platform::admin.dashboard', [
            'overview' => $dashboard->overview(DashboardPeriodData::fromRequest($request)),
            'admin' => $request->user('admin'),
        ]);
    }
}
