<?php

namespace App\Modules\Affiliates\Events;

use App\Modules\Affiliates\Models\AffiliateConversion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Affiliate program domain event (notified through SendAffiliateNotifications).
 */
final class AffiliateConversionReversed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AffiliateConversion $conversion) {}
}
