<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\ComplianceProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store submitted (or, after "needs action" / "rejected", re-submitted) its compliance
 * center for review (VEN-084). Admins who decide KYC are notified.
 */
final class ComplianceSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ComplianceProfile $profile,
        public readonly bool $resubmitted = false,
    ) {}
}
