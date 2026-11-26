<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Modules\Affiliates\DataTables\PortalPromoCodesDataTable;
use App\Modules\Affiliates\Http\Controllers\Concerns\ResolvesSignedInAffiliate;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Affiliate › Links & promo codes (AFF-010): referral link (copy, clicks, conversion
 * rate) and the promo codes the admin issued to this affiliate.
 */
class LinksController extends Controller
{
    use ResolvesSignedInAffiliate;

    public function __construct(private readonly AffiliatePortalService $portal) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        return view('affiliates::portal.links', [
            'affiliate' => $affiliate,
            'overview' => $this->portal->overview($affiliate),
        ]);
    }

    public function promoCodes(Request $request, PortalPromoCodesDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate($request))->toJsonFromRequest($request);
    }
}
