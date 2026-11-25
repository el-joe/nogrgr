<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\ComplianceProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin decided on a store's compliance (verified / needs action / rejected).
 */
final class ComplianceReviewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly ComplianceProfile $profile) {}
}
