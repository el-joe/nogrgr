<?php

namespace App\Modules\Ai\DTOs;

use App\Modules\Ai\Enums\VideoAdAspect;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdPresenter;
use App\Modules\Ai\Enums\VideoAdTone;
use App\Modules\Ai\Enums\VideoAdType;
use App\Modules\Ai\Enums\VideoAdVoice;
use App\Shared\DTOs\BaseDTO;

/**
 * The four wizard steps of the AI Advertising Studio (VEN-010c): product, video type,
 * people and voice, platform and size (+ optional target market and notes).
 */
final class VideoAdRequestData extends BaseDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly VideoAdType $type,
        public readonly VideoAdPresenter $presenter,
        public readonly VideoAdVoice $voice,
        public readonly VideoAdTone $tone,
        public readonly string $locale,
        public readonly VideoAdPlatform $platform,
        public readonly VideoAdAspect $aspect,
        public readonly int $duration,
        public readonly ?int $countryId,
        public readonly ?string $notes,
    ) {}
}
