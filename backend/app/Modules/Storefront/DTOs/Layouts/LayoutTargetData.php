<?php

namespace App\Modules\Storefront\DTOs\Layouts;

use App\Modules\Storefront\Enums\StoreLayoutScope;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Models\Theme;

/**
 * What the page builder edits (VEN-032): the store home of the default scope or one selling
 * country (with the theme that scope renders), or one store page.
 */
final readonly class LayoutTargetData
{
    public function __construct(
        public StoreLayoutScope $scope,
        public string $slot,
        public string $key,
        public ?int $countryId = null,
        public ?Theme $theme = null,
        public ?StorePage $page = null,
    ) {}

    public function isHome(): bool
    {
        return $this->scope === StoreLayoutScope::Home;
    }
}
