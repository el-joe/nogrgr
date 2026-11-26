<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Modules\Affiliates\DataTables\PortalConversionsDataTable;
use App\Modules\Affiliates\Enums\ConversionStatus;
use App\Modules\Affiliates\Http\Controllers\Concerns\ResolvesSignedInAffiliate;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Affiliate › Conversions (AFF-011): own conversions with a status filter.
 */
class ConversionController extends Controller
{
    use ResolvesSignedInAffiliate;

    public function __construct(private readonly AffiliatePortalService $portal) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        return view('affiliates::portal.conversions', [
            'overview' => $this->portal->overview($affiliate),
            'statuses' => ConversionStatus::options(),
        ]);
    }

    public function data(Request $request, PortalConversionsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate($request))->toJsonFromRequest($request, PortalConversionsDataTable::FILTERS);
    }
}
