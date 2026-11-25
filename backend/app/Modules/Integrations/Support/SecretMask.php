<?php

namespace App\Modules\Integrations\Support;

/**
 * Masked display of a stored secret: bullets + last 4 characters (long values only),
 * so admins can recognise a key without it ever reaching the browser.
 */
final class SecretMask
{
    public const BULLETS = '••••••••';

    public static function mask(?string $secret): ?string
    {
        if ($secret === null || $secret === '') {
            return null;
        }

        return mb_strlen($secret) >= 12 ? self::BULLETS.' '.mb_substr($secret, -4) : self::BULLETS;
    }
}
