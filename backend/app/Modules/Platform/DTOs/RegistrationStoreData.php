<?php

namespace App\Modules\Platform\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Complete registration › store step (WEB-018a).
 */
final class RegistrationStoreData extends BaseDTO
{
    public function __construct(
        public readonly string $storeName,
        public readonly string $slug,
        public readonly ?string $customDomain = null,
        public readonly int $profitPercentBps = 0,
    ) {}
}
