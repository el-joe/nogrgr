<?php

namespace App\Modules\Identity\Exceptions;

use RuntimeException;

/**
 * Social sign-in could not finish. `cancelled` = the visitor backed out at the
 * provider; anything else is shown as a generic error (details are only logged).
 */
final class SocialAuthException extends RuntimeException
{
    public function __construct(public readonly bool $cancelled, ?string $detail = null, public readonly ?string $userMessage = null)
    {
        parent::__construct($detail ?? ($cancelled ? 'cancelled' : 'failed'));
    }

    public static function cancelled(): self
    {
        return new self(true);
    }

    public static function failed(string $detail, ?string $userMessage = null): self
    {
        return new self(false, $detail, $userMessage);
    }
}
