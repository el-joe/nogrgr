<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * One item (record / setting / static key) of a paid store translation run: translated
 * (done, with the hash of the source it was made from) or failed (retried on resume).
 */
enum TranslationItemStatus: string
{
    use HasEnumHelpers;

    case Done = 'done';
    case Failed = 'failed';
}
