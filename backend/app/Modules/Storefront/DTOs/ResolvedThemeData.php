<?php

namespace App\Modules\Storefront\DTOs;

use App\Modules\Storefront\Enums\ThemeFamily;

/**
 * The theme a storefront request renders with: catalogue slug, family (inner pages are
 * shared per family) and the home variant (1 for Ecommet, 1–6 for Elora / Souqify).
 * name: the catalogue display name, set for a vendor theme preview (preview bar).
 */
final readonly class ResolvedThemeData
{
    public function __construct(
        public string $slug,
        public ThemeFamily $family,
        public int $variant,
        public ?string $name = null,
    ) {}

    /**
     * @return array{slug: string, family: string, variant: int}
     */
    public function toArray(): array
    {
        return ['slug' => $this->slug, 'family' => $this->family->value, 'variant' => $this->variant];
    }
}
