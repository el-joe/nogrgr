<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Typed AI provider failures (docs/plan/01-modules.md §A6): each maps to the HTTP
 * status our endpoints answer with and a user-friendly message.
 */
enum AiErrorType: string
{
    use HasEnumHelpers;

    case NotConfigured = 'not_configured';
    case QuotaExhausted = 'quota_exhausted';
    case AuthFailed = 'auth_failed';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
    case InvalidResponse = 'invalid_response';

    public function httpStatus(): int
    {
        return match ($this) {
            self::NotConfigured => 422,
            self::QuotaExhausted => 402,
            self::AuthFailed => 502,
            self::RateLimited => 429,
            self::Unavailable => 503,
            self::InvalidResponse => 502,
        };
    }

    /**
     * Classify a provider HTTP status (+ OpenAI error code when present).
     */
    public static function fromResponse(int $status, ?string $code = null): self
    {
        return match (true) {
            $code === 'insufficient_quota', $status === 402 => self::QuotaExhausted,
            in_array($status, [401, 403], true) => self::AuthFailed,
            $status === 429 => self::RateLimited,
            $status >= 500 => self::Unavailable,
            default => self::InvalidResponse,
        };
    }
}
