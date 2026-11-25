<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum AddonType: string
{
    use HasEnumHelpers;

    case Language = 'language';
    case AiTranslation = 'ai_translation';
}
