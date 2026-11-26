<?php

namespace App\Modules\Affiliates\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * AFF-013 profile: name, phone (E.164) and panel language.
 */
final class AffiliateProfileData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone,
        public readonly string $locale,
    ) {}
}
