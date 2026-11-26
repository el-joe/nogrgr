<?php

namespace App\Modules\Affiliates\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * AFF-002 partner sign-up.
 */
final class AffiliateRegistrationData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $password,
        public readonly string $locale,
    ) {}
}
