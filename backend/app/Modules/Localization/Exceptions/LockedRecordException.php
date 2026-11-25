<?php

namespace App\Modules\Localization\Exceptions;

use DomainException;

/**
 * A protected record was changed: Arabic / English (always on, always free) or the
 * USD base currency. Controllers answer it as a 422 with the translated message.
 */
final class LockedRecordException extends DomainException
{
    public static function coreLanguage(): self
    {
        return new self(__('localization::admin.languages.locked'));
    }

    public static function baseCurrency(): self
    {
        return new self(__('localization::admin.currencies.locked'));
    }
}
