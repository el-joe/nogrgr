<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Editor row state for one key in the selected language: translated from the files,
 * changed by an admin override (stored centrally, survives deploys) or missing.
 */
enum TranslationKeyStatus: string
{
    use HasEnumHelpers;

    case Translated = 'translated';
    case Overridden = 'overridden';
    case Missing = 'missing';

    public function tone(): string
    {
        return match ($this) {
            self::Translated => 'success',
            self::Overridden => 'info',
            self::Missing => 'warning',
        };
    }
}
