<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Typed failure of a "Test connection" call, mapped to a user-friendly message.
 */
enum ConnectionErrorType: string
{
    use HasEnumHelpers;

    case Misconfigured = 'misconfigured';
    case AuthFailed = 'auth_failed';
    case RateLimited = 'rate_limited';
    case QuotaExhausted = 'quota_exhausted';
    case Unreachable = 'unreachable';
    case UnexpectedResponse = 'unexpected_response';

    /**
     * Classify an HTTP status from a provider.
     */
    public static function fromStatus(int $status): self
    {
        return match (true) {
            in_array($status, [401, 403], true) => self::AuthFailed,
            $status === 429 => self::RateLimited,
            $status >= 500 => self::Unreachable,
            default => self::UnexpectedResponse,
        };
    }
}
