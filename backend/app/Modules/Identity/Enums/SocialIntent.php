<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why the visitor went to Google / Apple: sign up for a (new) store, or sign in.
 */
enum SocialIntent: string
{
    use HasEnumHelpers;

    case Register = 'register';
    case Login = 'login';
}
