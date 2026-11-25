<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * On/off capabilities of a plan (AI flags and add-ons).
 */
enum PlanFeature: string
{
    use HasEnumHelpers;

    case AiTranslation = 'ai_translation';
    case AiSocialPosts = 'ai_social_posts';
    case CustomThemes = 'custom_themes';
    case MobileApp = 'mobile_app';
}
