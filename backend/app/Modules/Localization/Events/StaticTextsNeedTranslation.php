<?php

namespace App\Modules\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * New or changed base static texts reached a store that bought extra languages: the
 * P10 AI translation module translates `keys` into `locales` (skipping locked and
 * manually edited rows) — decision #45.
 */
final class StaticTextsNeedTranslation
{
    use Dispatchable;

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $locales
     */
    public function __construct(
        public readonly int|string $tenantId,
        public readonly array $keys,
        public readonly array $locales,
    ) {}
}
