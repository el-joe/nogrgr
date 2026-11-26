<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Services\ConversionService;
use App\Modules\Affiliates\Services\ReferralTracker;
use App\Modules\Platform\Events\RegistrationCompleted;
use Throwable;

/**
 * RegistrationCompleted → pending conversion for the referring affiliate (click saved
 * on the registration when the plan was chosen, else the visitor's referral cookie).
 * Attribution never breaks a sign-up: failures are reported.
 */
class RecordReferralOnRegistration
{
    public function __construct(
        private readonly ConversionService $conversions,
        private readonly ReferralTracker $tracker,
    ) {}

    public function handle(RegistrationCompleted $event): void
    {
        try {
            $fallback = $this->tracker->currentClickId(request());
            $this->conversions->recordSignup($event->registration, $event->tenant, $fallback);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
