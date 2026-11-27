<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An affiliate (or the store for its vendor affiliate) asked for a payout; admins who
 * manage payouts are notified (SendAffiliateNotifications, decision #124).
 */
final class AffiliatePayoutRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AffiliatePayoutRequest $payoutRequest) {}
}
