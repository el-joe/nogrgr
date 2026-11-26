<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\DevicePlatform;
use App\Shared\DTOs\BaseDTO;

/**
 * A mobile app install reporting itself (Mobile API `devices` endpoint).
 */
final class CustomerDeviceData extends BaseDTO
{
    public function __construct(
        public readonly string $deviceId,
        public readonly DevicePlatform $platform,
        public readonly ?string $pushToken = null,
        public readonly bool $pushEnabled = true,
        public readonly ?string $appVersion = null,
        public readonly ?string $osVersion = null,
        public readonly ?string $model = null,
        public readonly ?string $locale = null,
    ) {}
}
