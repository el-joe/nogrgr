<?php

namespace App\Modules\Affiliates\Http\ViewComposers;

use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Modules\Affiliates\Services\PayoutRequestService;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Data of the vendor dashboard partner banner (VEN-009b, view affiliates::vendor._partner):
 * null unless the signed-in staff member may see the partner program.
 */
final class VendorPartnerWidgetComposer
{
    public function __construct(
        private readonly AffiliateAccountService $accounts,
        private readonly AffiliatePortalService $portal,
        private readonly PayoutRequestService $requests,
    ) {}

    public function compose(View $view): void
    {
        $staff = Auth::guard('vendor')->user();
        $tenant = tenant();

        if ($staff === null || ! $tenant instanceof Tenant || ! $staff->can('partner.view')) {
            $view->with('partner', null);

            return;
        }

        $affiliate = $this->accounts->createForTenant($tenant);

        $view->with('partner', [
            'link' => $this->portal->referralLink($affiliate),
            'commission' => $this->accounts->commissionLabel($affiliate),
            'balance' => $this->requests->requestable($affiliate)->format(),
            'url' => vendor_route('partner.index'),
        ]);
    }
}
