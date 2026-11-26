<?php

namespace App\Modules\Affiliates\DTOs;

use App\Modules\Affiliates\Enums\SsoFailure;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\DTOs\BaseDTO;

/**
 * Outcome of consuming an affiliate SSO token: the affiliate, or why it was refused.
 */
final class SsoResult extends BaseDTO
{
    public function __construct(
        public readonly ?Affiliate $affiliate,
        public readonly ?SsoFailure $failure = null,
    ) {}

    public static function failed(SsoFailure $failure): self
    {
        return new self(null, $failure);
    }
}
