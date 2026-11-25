<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why the visitor went to Google / Apple: sign up for a (new) store, sign in, or link
 * the provider to the signed-in owner's account (owner portal profile, OWN-006).
 */
enum SocialIntent: string
{
    use HasEnumHelpers;

    case Register = 'register';
    case Login = 'login';
    case Link = 'link';
}
