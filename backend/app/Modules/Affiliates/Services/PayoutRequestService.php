<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\PayoutRequestData;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PortalStatsRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Payout requests made by the affiliate itself (AFF-012a) or by the store for its vendor
 * affiliate (VEN-055a):
 *   requestable = balance − open (pending) requests, never below zero;
 *   a request needs an active account, saved payout details (vendor affiliates are
 *   paid through the store and need none), minimum payout ≤ amount ≤ requestable.
 * The admin pays or rejects it on ADM-062 (PayoutService).
 */
class PayoutRequestService extends BaseService
{
    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly PayoutRequestRepositoryContract $requests,
        private readonly PortalStatsRepositoryContract $stats,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function requestable(Affiliate $affiliate): Money
    {
        return Money::ofMinor(max(0, (int) $affiliate->balance - $this->stats->pendingRequestsTotal($affiliate->id)));
    }

    public function minimum(): Money
    {
        return $this->settings->minPayout();
    }

    public function canRequest(Affiliate $affiliate): bool
    {
        return $affiliate->isActive() && $this->requestable($affiliate)->minor >= $this->minimum()->minor;
    }

    public function request(Affiliate $affiliate, PayoutRequestData $data, bool $requiresDetails = true): AffiliatePayoutRequest
    {
        return $this->transaction(function () use ($affiliate, $data, $requiresDetails): AffiliatePayoutRequest {
            $locked = $this->affiliates->lock($affiliate->id);

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['amount' => __('affiliates::panel.payouts.errors.inactive')]);
            }

            if ($requiresDetails && $locked->payout_method === null) {
                throw ValidationException::withMessages(['amount' => __('affiliates::panel.payouts.errors.no_details')]);
            }

            $requestable = $this->requestable($locked);
            $minimum = $this->minimum();

            if ($data->amount > $requestable->minor) {
                throw ValidationException::withMessages(['amount' => __('affiliates::panel.payouts.errors.above_requestable', ['amount' => $requestable->format()])]);
            }

            if ($data->amount < $minimum->minor) {
                throw ValidationException::withMessages(['amount' => __('affiliates::panel.payouts.errors.below_minimum', ['amount' => $minimum->format()])]);
            }

            /** @var AffiliatePayoutRequest $request */
            $request = $this->requests->create([
                'affiliate_id' => $locked->id,
                'amount' => $data->amount,
                'status' => PayoutRequestStatus::Pending,
                'note' => $data->note,
            ]);

            return $request;
        }, (new AffiliatePayoutRequest)->getConnectionName());
    }
}
