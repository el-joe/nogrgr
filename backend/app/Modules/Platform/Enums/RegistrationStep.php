<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum RegistrationStep: string
{
    use HasEnumHelpers;

    case Payment = 'payment';
    case EmailVerification = 'email_verification';
    case StoreDetails = 'store_details';
    case Done = 'done';
}
