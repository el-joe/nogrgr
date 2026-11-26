<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Affiliate program domain event (notified through SendAffiliateNotifications).
 */
final class AffiliatePayoutRequestRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AffiliatePayoutRequest $payoutRequest) {}
}
