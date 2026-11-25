<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Central-website sign-up (WEB-016): who signs up and for which plan. Status defaults to
 * "email unverified"; the payment step starts it as payment_pending instead.
 *
 * @phpstan-type Meta array<string, mixed>
 */
final class PendingRegistrationData extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $meta  coupon, referral, compliance acceptance…
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?int $planId = null,
        public readonly ?BillingTerm $term = null,
        public readonly ?string $locale = null,
        public readonly RegistrationStatus $status = RegistrationStatus::EmailUnverified,
        public readonly array $meta = [],
    ) {}
}
