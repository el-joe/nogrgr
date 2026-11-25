<?php

namespace App\Modules\Integrations\Support;

/**
 * OAuth callback URL admins paste into the Google Cloud / Apple developer consoles.
 * Fixed central path so it never changes when the social-login controllers ship.
 */
final class SocialRedirectUri
{
    public const PATH = '/auth/%s/callback';

    public static function for(string $provider): string
    {
        return rtrim((string) config('app.url'), '/').sprintf(self::PATH, $provider);
    }
}
