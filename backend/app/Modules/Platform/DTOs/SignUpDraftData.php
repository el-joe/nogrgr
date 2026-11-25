<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Sign-up kept in the session until the plan is chosen (WEB-016 → WEB-016b): nothing is
 * written to vendor_registrations before the visitor commits to a plan. A Google /
 * Apple sign-up or a signed-in owner (OWN-007) carries a proven e-mail.
 */
final class SignUpDraftData extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $locale,
        public readonly string $complianceAcceptedAt,
        public readonly bool $emailVerified = false,
        public readonly ?int $ownerId = null,
        public readonly ?string $ownerName = null,
        public readonly ?string $socialProvider = null,
        public readonly ?string $storeName = null,
        public readonly ?string $slug = null,
        public readonly ?int $defaultCountryId = null,
    ) {}

}
