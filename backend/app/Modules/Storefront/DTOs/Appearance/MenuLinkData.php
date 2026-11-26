<?php

namespace App\Modules\Storefront\DTOs\Appearance;

use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;

/**
 * Store menu link input (VEN-034d header links / VEN-034e footer links).
 */
final readonly class MenuLinkData
{
    /**
     * @param  array<string, string>  $label  locale => text
     */
    public function __construct(
        public MenuLinkPlacement $placement,
        public array $label,
        public string $url,
        public ?FooterColumn $footerColumn = null,
        public bool $isActive = true,
    ) {}
}
