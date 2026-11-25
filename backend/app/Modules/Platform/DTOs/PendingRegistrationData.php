<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Central-website sign-up (WEB-016): who signs up and for which plan. Status defaults to
 * "email unverified"; the payment step starts it as payment_pending instead. A signed-in
 * owner ("Add another store", OWN-007) or a Google / Apple sign-up arrives with a proven
 * e-mail (emailVerified) and, for owners, the name / password hash / store already known.
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
        public readonly ?int $ownerId = null,
        public readonly bool $emailVerified = false,
        public readonly ?string $ownerName = null,
        public readonly ?string $ownerPasswordHash = null,
        public readonly ?string $storeName = null,
        public readonly ?string $slug = null,
    ) {}
}
