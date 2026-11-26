<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Identity\Models\Affiliate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Affiliate program domain event (notified through SendAffiliateNotifications).
 */
final class AffiliateActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Affiliate $affiliate) {}
}
