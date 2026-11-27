<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\Dashboard\VendorDashboardWidgetService;
use App\Modules\Platform\Services\OnboardingService;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Modules\Reporting\Enums\DashboardPeriod;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Services\VendorDashboardPerformanceService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor panel home (VEN-009): welcome header, store status hero ("Store power" = setup
 * progress), setup progress widget (VEN-008), and the permission-aware widgets — pay the
 * platform alert (009a), opportunities + flash-sale modal (009c), new-in central products
 * (009d), live store preview by country (009f) — plus the partner banner (009b) and
 * "Your store's performance" (KPIs + period selector + gross vs collected + status mix +
 * latest orders, client frame 12:11267) for members with analytics.view; the latest
 * orders link to the order page only with orders.view.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly VendorDashboardWidgetService $widgets,
        private readonly VendorDashboardPerformanceService $performance,
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
        $countryId = filter_var($country, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $canOpenOrders = $staff->can('orders.view');
        $performance = $staff->can('analytics.view')
            ? $this->performance->summary(
                (int) $tenant->getTenantKey(),
                DashboardPeriod::fromQuery($request->query('period')),
                $countryId,
                $canOpenOrders ? static fn (OrderFact $fact): string => vendor_route('sales.orders.show', ['order' => $fact->order_uuid]) : null,
            )
            : null;

        return view('platform::vendor.dashboard', [
            'tenant' => $tenant,
            'staff' => $staff,
            'widgets' => $this->widgets->widgets($tenant, $staff, is_string($country) ? $country : null),
            'progress' => $this->onboarding->progress($tenant),
            'storeUrl' => StoreSettingsService::storeUrl($tenant),
            'performance' => $performance,
            'periods' => DashboardPeriod::cases(),
        ]);
    }
}
