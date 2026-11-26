<?php

namespace App\Modules\Storefront\DTOs;

use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Services\Themes\ThemePageService;

/**
 * One scope of the vendor themes pages (VEN-030 tab / VEN-031 row): the store default
 * (countryId null) or a selling country, with the theme it renders and whether the
 * country has its own theme or follows the store default.
 */
final readonly class ThemePageRowData
{
    public function __construct(
        public ?int $countryId,
        public string $label,
        public ?Theme $theme,
        public bool $ownTheme,
    ) {}

    public function scope(): string
    {
        return $this->countryId === null ? ThemePageService::DEFAULT_SCOPE : (string) $this->countryId;
    }
}
