<?php

namespace App\Modules\Api\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One platform's release settings of the store app: lowest version that may still run
 * (below = forced update), the latest published version (below = optional update) and
 * the store listing URL.
 */
final class PlatformReleaseData extends BaseDTO
{
    public function __construct(
        public readonly ?string $minVersion = null,
        public readonly ?string $latestVersion = null,
        public readonly ?string $storeUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $value
     */
    public static function fromSetting(?array $value): self
    {
        $value ??= [];

        return new self(
            minVersion: self::version($value['min_version'] ?? null),
            latestVersion: self::version($value['latest_version'] ?? null),
            storeUrl: is_string($value['store_url'] ?? null) && $value['store_url'] !== '' ? $value['store_url'] : null,
        );
    }

    /**
     * @return array{min_version: string|null, latest_version: string|null, store_url: string|null}
     */
    public function toSetting(): array
    {
        return ['min_version' => $this->minVersion, 'latest_version' => $this->latestVersion, 'store_url' => $this->storeUrl];
    }

    private static function version(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d+(\.\d+){0,3}$/', $value) === 1 ? $value : null;
    }
}
