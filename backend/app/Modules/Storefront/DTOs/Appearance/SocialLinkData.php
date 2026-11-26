<?php

namespace App\Modules\Storefront\DTOs\Appearance;

use App\Modules\Storefront\Enums\SocialNetwork;

/**
 * Store social profile input (VEN-034f).
 */
final readonly class SocialLinkData
{
    public function __construct(
        public SocialNetwork $network,
        public string $url,
        public bool $isActive = true,
    ) {}
}
