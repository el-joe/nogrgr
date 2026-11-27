<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Why the visitor went to Google / Apple: sign up for a (new) store, sign in, or link
 * the provider to the signed-in owner's account (owner portal profile, OWN-006), or a
 * store's shopper (theme sign-in, through the platform callback).
 */
enum SocialIntent: string
{
    use HasEnumHelpers;

    case Register = 'register';
    case Login = 'login';
    case Link = 'link';

    /** A store's shopper signing in on the theme (the result is handed back to the store host). */
    case Customer = 'customer';
}
