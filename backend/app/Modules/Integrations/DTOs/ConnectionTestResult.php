<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\ConnectionErrorType;
use App\Modules\Integrations\Enums\ConnectionStatus;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * Outcome of a "Test connection" / "Send test" action, persisted as the card's
 * last test and returned to the panel (never contains secrets).
 */
final class ConnectionTestResult extends BaseDTO
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?ConnectionErrorType $error = null,
        public readonly ?string $detail = null,
        public readonly ?string $testedAt = null,
    ) {}

    public static function success(?string $detail = null): self
    {
        return new self(true, null, $detail, Carbon::now()->toIso8601String());
    }

    public static function failure(ConnectionErrorType $error, ?string $detail = null): self
    {
        return new self(false, $error, $detail !== null ? mb_substr($detail, 0, 300) : null, Carbon::now()->toIso8601String());
    }

    public function status(): ConnectionStatus
    {
        return $this->ok ? ConnectionStatus::Connected : ConnectionStatus::Failed;
    }

    public function message(): string
    {
        return $this->ok
            ? __('integrations::admin.test.success')
            : __('integrations::admin.test.failed', ['reason' => $this->error?->label() ?? '']);
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromStored(?array $stored): ?self
    {
        if ($stored === null || ! array_key_exists('ok', $stored)) {
            return null;
        }

        return new self(
            (bool) $stored['ok'],
            ConnectionErrorType::tryFrom((string) ($stored['error'] ?? '')),
            is_string($stored['detail'] ?? null) ? $stored['detail'] : null,
            is_string($stored['tested_at'] ?? null) ? $stored['tested_at'] : null,
        );
    }
}
