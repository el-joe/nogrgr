<?php

namespace App\Modules\Affiliates\Http\Controllers\Vendor;

use App\Modules\Affiliates\DataTables\PortalConversionsDataTable;
use App\Modules\Affiliates\DataTables\PortalPayoutsDataTable;
use App\Modules\Affiliates\Http\Requests\Portal\PayoutRequestFormRequest;
use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Modules\Affiliates\Services\AffiliateSsoService;
use App\Modules\Affiliates\Services\PayoutRequestService;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Partner program (VEN-055 / 055a, Figma Vendor Panel (new screens) 87:111264):
 * the store's auto-created vendor affiliate — balance, clicks, conversions, earned,
 * referral link + promo code, conversions and payouts lists, "Withdraw" (payout
 * request) and "Open affiliate dashboard" (one-time SSO into the affiliate panel).
 */
class PartnerProgramController extends Controller
{
    public function __construct(
        private readonly AffiliateAccountService $accounts,
        private readonly AffiliatePortalService $portal,
        private readonly PayoutRequestService $requests,
        private readonly AffiliateSsoService $sso,
    ) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate();

        return view('affiliates::vendor.partner', [
            'affiliate' => $affiliate,
            'overview' => $this->portal->overview($affiliate),
            'promoCode' => $this->portal->activePromoCode($affiliate),
            'canManage' => (bool) $request->user('vendor')?->can('partner.manage'),
        ]);
    }

    public function conversions(Request $request, PortalConversionsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate())->toJsonFromRequest($request, PortalConversionsDataTable::FILTERS);
    }

    public function payouts(Request $request, PortalPayoutsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate())->toJsonFromRequest($request);
    }

    public function withdraw(PayoutRequestFormRequest $request): JsonResponse
    {
        $payoutRequest = $this->requests->request($this->affiliate(), $request->toData(), requiresDetails: false);

        return JsonResponder::created(
            __('affiliates::panel.payouts.requested', ['amount' => $payoutRequest->money()->format()]),
            ['id' => $payoutRequest->id],
            vendor_route('partner.index'),
        );
    }

    public function sso(): RedirectResponse
    {
        return redirect()->away($this->sso->issueFor($this->tenant()));
    }

    private function affiliate(): Affiliate
    {
        return $this->accounts->createForTenant($this->tenant());
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
