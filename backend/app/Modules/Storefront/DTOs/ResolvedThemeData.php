<?php

namespace App\Modules\Storefront\DTOs;

use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Models\Theme;

/**
 * The theme a storefront request renders with: catalogue slug, family (inner pages are
 * shared per family) and the home variant (1 for Ecommet, 1–6 for Elora / Souqify).
 * name: the catalogue display name, set for a vendor theme preview (preview bar).
 * id: the catalogue theme id (null only when the catalogue is not seeded).
 * customVersionId: the store's custom Twig theme version rendering the themed pages
 * (decision #121); slug / family / variant are then its built-in fallback theme, used for
 * the secure pages (checkout, account) and whenever the custom theme fails to render.
 */
final readonly class ResolvedThemeData
{
    public function __construct(
        public string $slug,
        public ThemeFamily $family,
        public int $variant,
        public ?string $name = null,
        public ?int $customVersionId = null,
        public ?int $id = null,
    ) {}

    public static function fromTheme(Theme $theme, bool $withName = false): self
    {
        return new self($theme->slug, $theme->family, (int) $theme->variant, $withName ? (string) $theme->name : null, null, (int) $theme->id);
    }

    /**
     * Every Elora variant (Essence, Bloom, Noir, Coral, Breeze, Velvet) shares the Essence (v1)
     * inner pages and components; only home, header, footer and colours differ per variant.
     */
    public function isElora(): bool
    {
        return $this->family === ThemeFamily::Elora;
    }

    public function isCustom(): bool
    {
        return $this->customVersionId !== null;
    }

    public function withCustom(?int $versionId, ?string $name = null): self
    {
        return new self($this->slug, $this->family, $this->variant, $name ?? $this->name, $versionId, $this->id);
    }

    /**
     * @return array{id: int|null, slug: string, family: string, variant: int, custom: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'slug' => $this->slug, 'family' => $this->family->value, 'variant' => $this->variant, 'custom' => $this->isCustom()];
    }
}
