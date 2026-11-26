<?php

namespace App\Modules\Api\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * What the app must do with its version (bootstrap `app.update`): nothing, offer the
 * update, or block until updated (below the store's minimum version).
 */
enum AppUpdateState: string
{
    use HasEnumHelpers;

    case None = 'none';
    case Optional = 'optional';
    case Required = 'required';
}
