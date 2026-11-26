<?php

namespace App\Modules\Ai\DTOs;

use App\Modules\Ai\Enums\AiFeature;
use App\Shared\DTOs\BaseDTO;

/**
 * Who an AI call is made for (usage log row): the feature, the store (null = platform)
 * and an optional subject such as "run:12" or "product:5".
 */
final class AiUsageContext extends BaseDTO
{
    public function __construct(
        public readonly AiFeature $feature,
        public readonly ?int $tenantId = null,
        public readonly ?string $subject = null,
    ) {}
}
