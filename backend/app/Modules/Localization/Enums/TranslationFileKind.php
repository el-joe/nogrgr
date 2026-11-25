<?php

namespace App\Modules\Localization\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Kinds of translation "files" the admin editor works on: the storefront static text
 * base set (copied into every store), panel PHP groups (`lang/*` and module
 * `resources/lang/*`) and the JSON strings file.
 */
enum TranslationFileKind: string
{
    use HasEnumHelpers;

    case Storefront = 'storefront';
    case Php = 'php';
    case Json = 'json';
}
