<?php

namespace App\Modules\Api\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * Tokens handed to the app after sign-in / sign-up / refresh.
 */
final class TokenPairData extends BaseDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly Carbon $accessExpiresAt,
        public readonly string $refreshToken,
        public readonly Carbon $refreshExpiresAt,
    ) {}

    /**
     * @return array{token_type: string, access_token: string, expires_at: string, expires_in: int, refresh_token: string, refresh_expires_at: string}
     */
    public function toResponse(): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->accessToken,
            'expires_at' => $this->accessExpiresAt->toIso8601String(),
            'expires_in' => (int) max(0, now()->diffInSeconds($this->accessExpiresAt)),
            'refresh_token' => $this->refreshToken,
            'refresh_expires_at' => $this->refreshExpiresAt->toIso8601String(),
        ];
    }
}
