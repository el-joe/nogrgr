<?php

namespace App\Modules\Affiliates\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why an affiliate SSO link (AFF-007) was refused.
 */
enum SsoFailure: string
{
    use HasEnumHelpers;

    case Invalid = 'invalid';
    case Used = 'used';
    case Expired = 'expired';
}
