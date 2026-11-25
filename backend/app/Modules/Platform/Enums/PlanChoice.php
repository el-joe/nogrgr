<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Plan step (WEB-016b): start with the plan's free trial, or pay the first term now.
 */
enum PlanChoice: string
{
    use HasEnumHelpers;

    case Trial = 'trial';
    case Paid = 'paid';
}
