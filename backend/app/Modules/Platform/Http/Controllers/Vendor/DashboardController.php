<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Dashboard\VendorDashboardWidgetService;
use App\Modules\Platform\Services\OnboardingService;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor panel home (VEN-009): welcome header, store status hero ("Store power" = setup
 * progress), setup progress widget (VEN-008), and the permission-aware widgets — pay the
 * platform alert (009a), opportunities + flash-sale modal (009c), new-in central products
 * (009d), live store preview by country (009f) — plus the partner banner (009b).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly VendorDashboardWidgetService $widgets,
    ) {}

    public function home(): RedirectResponse
    {
        return redirect()->to(vendor_route('dashboard'));
    }

    public function show(Request $request): View
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');
        $country = $request->query('country');

        return view('platform::vendor.dashboard', [
            'tenant' => $tenant,
            'staff' => $staff,
            'widgets' => $this->widgets->widgets($tenant, $staff, is_string($country) ? $country : null),
            'progress' => $this->onboarding->progress($tenant),
            'storeUrl' => StoreSettingsService::storeUrl($tenant),
        ]);
    }
}
