<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a store's static text value came from (decision #45): the platform base set
 * (ar/en, kept in step with deploys), the paid AI translation run (P10) or a manual
 * edit by the vendor. Manual values are never overwritten by sync or AI.
 */
enum StaticTextSource: string
{
    use HasEnumHelpers;

    case Base = 'base';
    case Ai = 'ai';
    case Manual = 'manual';

    public function isManual(): bool
    {
        return $this === self::Manual;
    }
}
