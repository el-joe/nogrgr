<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum InvoiceType: string
{
    use HasEnumHelpers;

    case Subscription = 'subscription';
    case Renewal = 'renewal';
    case LanguageAddon = 'language_addon';
    case AiTranslationAddon = 'ai_translation_addon';
    case Manual = 'manual';

    public static function forAddon(AddonType $type): self
    {
        return match ($type) {
            AddonType::Language => self::LanguageAddon,
            AddonType::AiTranslation => self::AiTranslationAddon,
        };
    }
}
