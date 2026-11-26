<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Modules\Affiliates\Http\Controllers\Concerns\ResolvesSignedInAffiliate;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Affiliate › Dashboard (AFF-009): KPIs (USD), clicks & conversions chart, referral
 * link card with program terms, recent conversions.
 */
class DashboardController extends Controller
{
    use ResolvesSignedInAffiliate;

    public function __construct(private readonly AffiliatePortalService $portal) {}

    public function __invoke(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        return view('affiliates::portal.dashboard', [
            'affiliate' => $affiliate,
            'overview' => $this->portal->overview($affiliate),
            'chart' => $this->portal->chartRanges($affiliate),
            'recent' => $this->portal->recentConversions($affiliate),
            'promoCode' => $this->portal->activePromoCode($affiliate),
        ]);
    }
}
