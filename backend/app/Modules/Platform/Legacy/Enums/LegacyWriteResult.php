<?php

namespace App\Modules\Platform\Legacy\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What one mapped write did: a new row, an update of a row already mapped by a
 * previous run, or a match to a pre-existing row by its natural key (countries etc.).
 */
enum LegacyWriteResult: string
{
    use HasEnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Matched = 'matched';
}
