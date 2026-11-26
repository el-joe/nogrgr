<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why a store translation run exists: the paid language purchase (full run, switches the
 * language on) or the weekly delta (only new / changed content of an already bought
 * language; never switches a language on, owners hear only about failures).
 */
enum TranslationRunTrigger: string
{
    use HasEnumHelpers;

    case Purchase = 'purchase';
    case Delta = 'delta';
}
