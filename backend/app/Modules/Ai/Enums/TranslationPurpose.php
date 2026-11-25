<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Decision #44: custom translations (per-field buttons, single keys, suggestions) and
 * paid full-store translations each use their own configured provider.
 */
enum TranslationPurpose: string
{
    use HasEnumHelpers;

    case Custom = 'custom';
    case Paid = 'paid';

    public function configKey(): string
    {
        return match ($this) {
            self::Custom => 'ai.translation.custom_provider',
            self::Paid => 'ai.translation.paid_provider',
        };
    }
}
