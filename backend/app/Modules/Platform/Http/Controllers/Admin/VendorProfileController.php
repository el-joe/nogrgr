<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Api\Services\MobileAppPanelService;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DTOs\VendorCommissionData;
use App\Modules\Platform\DTOs\VendorStoreData;
use App\Modules\Platform\Enums\ImpersonationReason;
use App\Modules\Platform\Http\Requests\Admin\VendorCommissionRequest;
use App\Modules\Platform\Http\Requests\Admin\VendorStoreInfoRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\VendorProfileService;
use App\Modules\Platform\Services\VendorService;
use App\Modules\Reporting\Services\VendorStatsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Vendors › Vendor profile (ADM-041…041f): Overview, Plan & subscription,
 * Domains, Profit & commission, Stats & orders (ADM-041e), Compliance and Mobile app (ADM-041g) tabs (?tab=), the Edit store form and
 * the profit / commission form.
 */
class VendorProfileController extends Controller
{
    public const TABS = ['overview', 'plan', 'domains', 'commission', 'stats', 'compliance', 'mobile-app'];

    public function __construct(
        private readonly VendorService $vendors,
        private readonly VendorProfileService $profile,
    ) {}

    public function show(Request $request, Tenant $tenant, PlanRepositoryContract $plans, CountryService $countries, VendorStatsService $stats, MobileAppPanelService $mobileApp): View
    {
        $tab = $request->query('tab');
        $tab = is_string($tab) && in_array($tab, self::TABS, true) ? $tab : 'overview';

        return view('platform::admin.vendors.show', [
            'tenant' => $tenant,
            'tab' => $tab,
            'subscription' => $this->profile->currentSubscription($tenant),
            'domainCount' => $this->profile->domainCount($tenant),
            'canRetry' => $this->profile->canRetryProvisioning($tenant),
            'countries' => $countries->options(),
            'reasons' => ImpersonationReason::options(),
            'countryNames' => $tab === 'overview' ? $this->profile->countryNames($tenant) : null,
            'languages' => $tab === 'overview' ? $this->profile->languages($tenant) : [],
            'customDomain' => $tab === 'overview' ? $this->profile->customDomain($tenant) : null,
            'compliance' => in_array($tab, ['overview', 'compliance'], true) ? $this->profile->complianceProfile($tenant) : null,
            'history' => $tab === 'plan' ? $this->profile->subscriptionHistory($tenant) : collect(),
            'activePlans' => $tab === 'plan' ? $plans->options(activeOnly: true) : [],
            'terms' => BillingTerm::options(),
            'domainRequests' => $tab === 'domains' ? $this->profile->domainRequests($tenant) : collect(),
            'stats' => $tab === 'stats' ? $stats->overview((int) $tenant->id) : null,
            // ADM-041g: the store's app settings, read in its tenant context (operational stores only).
            'mobileApp' => $tab === 'mobile-app' && $tenant->status->isOperational() ? $mobileApp->page($tenant) : null,
        ]);
    }

    public function update(VendorStoreInfoRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->vendors->updateStore($tenant, VendorStoreData::fromArray($request->payload()));

        return JsonResponder::success(__('platform::vendors.profile.updated', ['name' => $tenant->name]), null, route('admin.vendors.show', $tenant->id));
    }

    public function commission(VendorCommissionRequest $request, Tenant $tenant): JsonResponse
    {
        $this->vendors->updateCommission($tenant, VendorCommissionData::fromArray($request->payload()));

        return JsonResponder::success(__('platform::vendors.commission.updated'));
    }
}
