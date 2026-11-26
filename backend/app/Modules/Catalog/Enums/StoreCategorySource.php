<?php

namespace App\Modules\Catalog\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Where a store category comes from (decision #74): the platform's central taxonomy
 * (read-only, the store only hides / orders it) or a category the store created.
 */
enum StoreCategorySource: string
{
    use HasEnumHelpers;

    case Central = 'central';
    case Store = 'store';
}
