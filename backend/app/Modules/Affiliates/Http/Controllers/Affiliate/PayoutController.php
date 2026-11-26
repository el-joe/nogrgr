<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Modules\Affiliates\DataTables\PortalPayoutRequestsDataTable;
use App\Modules\Affiliates\DataTables\PortalPayoutsDataTable;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Http\Controllers\Concerns\ResolvesSignedInAffiliate;
use App\Modules\Affiliates\Http\Requests\Portal\PayoutRequestFormRequest;
use App\Modules\Affiliates\Services\AffiliatePortalService;
use App\Modules\Affiliates\Services\PayoutRequestService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Affiliate › Payouts (AFF-012 / 012a): balance summary, payout requests and paid
 * payouts (two tables), "Request payout" modal.
 */
class PayoutController extends Controller
{
    use ResolvesSignedInAffiliate;

    public function __construct(
        private readonly AffiliatePortalService $portal,
        private readonly PayoutRequestService $requests,
    ) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        return view('affiliates::portal.payouts', [
            'affiliate' => $affiliate,
            'overview' => $this->portal->overview($affiliate),
            'statuses' => PayoutRequestStatus::options(),
        ]);
    }

    public function requestsData(Request $request, PortalPayoutRequestsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate($request))->toJsonFromRequest($request, PortalPayoutRequestsDataTable::FILTERS);
    }

    public function historyData(Request $request, PortalPayoutsDataTable $table): JsonResponse
    {
        return $table->for($this->affiliate($request))->toJsonFromRequest($request);
    }

    public function store(PayoutRequestFormRequest $request): JsonResponse
    {
        $payoutRequest = $this->requests->request($this->affiliate($request), $request->toData());

        return JsonResponder::created(
            __('affiliates::panel.payouts.requested', ['amount' => $payoutRequest->money()->format()]),
            ['id' => $payoutRequest->id],
            route('affiliate.payouts.index'),
        );
    }
}
