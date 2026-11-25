<?php

namespace App\Modules\Notifications\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Language filter of the template list: complete in ar + en, or missing one.
 */
enum EmailTemplateLocaleState: string
{
    use HasEnumHelpers;

    case Complete = 'complete';
    case MissingAr = 'missing_ar';
    case MissingEn = 'missing_en';

    public function missingLocale(): ?string
    {
        return match ($this) {
            self::Complete => null,
            self::MissingAr => 'ar',
            self::MissingEn => 'en',
        };
    }
}
