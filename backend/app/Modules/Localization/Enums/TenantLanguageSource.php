<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * How a store got a language: default (ar/en at provisioning), enabled for free,
 * or bought (paid add-on purchase).
 */
enum TenantLanguageSource: string
{
    use HasEnumHelpers;

    case Default = 'default';
    case Free = 'free';
    case Purchased = 'purchased';
}
