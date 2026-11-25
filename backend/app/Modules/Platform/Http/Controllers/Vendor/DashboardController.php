<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\OnboardingService;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor panel home (VEN-009 foundation): welcome header, store status hero ("Store
 * power" = setup progress) and the setup progress widget (VEN-008). Sales / orders KPI
 * cards join once their data exists (P5 / P8).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function home(): RedirectResponse
    {
        return redirect()->to(vendor_route('dashboard'));
    }

    public function show(Request $request): View
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return view('platform::vendor.dashboard', [
            'tenant' => $tenant,
            'staff' => $request->user('vendor'),
            'progress' => $this->onboarding->progress($tenant),
            'storeUrl' => StoreSettingsService::storeUrl($tenant),
        ]);
    }
}
