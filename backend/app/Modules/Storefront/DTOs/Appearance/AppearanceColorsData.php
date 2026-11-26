<?php

namespace App\Modules\Storefront\DTOs\Appearance;

use App\Modules\Storefront\Enums\StoreFont;

/**
 * Appearance › Colours & fonts (VEN-034b): brand colour (#rrggbb, null = theme default)
 * and the body font for left-to-right / right-to-left pages (null = theme default).
 */
final readonly class AppearanceColorsData
{
    public function __construct(
        public ?string $primary = null,
        public ?StoreFont $fontLtr = null,
        public ?StoreFont $fontRtl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $value  stored setting
     */
    public static function fromSetting(mixed $value): self
    {
        $value = is_array($value) ? $value : [];
        $primary = $value['primary'] ?? null;

        return new self(
            primary: is_string($primary) && preg_match('/^#[0-9a-f]{6}$/i', $primary) === 1 ? strtolower($primary) : null,
            fontLtr: StoreFont::tryFrom((string) ($value['font_ltr'] ?? '')),
            fontRtl: StoreFont::tryFrom((string) ($value['font_rtl'] ?? '')),
        );
    }

    public function isDefault(): bool
    {
        return $this->primary === null && $this->fontLtr === null && $this->fontRtl === null;
    }

    /**
     * @return array{primary: string|null, font_ltr: string|null, font_rtl: string|null}
     */
    public function toSetting(): array
    {
        return ['primary' => $this->primary, 'font_ltr' => $this->fontLtr?->value, 'font_rtl' => $this->fontRtl?->value];
    }
}
