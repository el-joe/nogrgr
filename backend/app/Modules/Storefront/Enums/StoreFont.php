<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Storefront body fonts a store can pick (Appearance › Colours & fonts, VEN-034b), one
 * for left-to-right and one for right-to-left pages. Only self-hosted @fontsource
 * families already in the project (no CDN, decision #89); each has its own Vite CSS
 * input with the @font-face rules, loaded only when chosen.
 */
enum StoreFont: string
{
    use HasEnumHelpers;

    case Outfit = 'outfit';
    case Inter = 'inter';
    case Manrope = 'manrope';
    case NotoSansArabic = 'noto-sans-arabic';
    case IbmPlexSansArabic = 'ibm-plex-sans-arabic';
    case Tajawal = 'tajawal';

    public function family(): string
    {
        return match ($this) {
            self::Outfit => 'Outfit',
            self::Inter => 'Inter',
            self::Manrope => 'Manrope',
            self::NotoSansArabic => 'Noto Sans Arabic',
            self::IbmPlexSansArabic => 'IBM Plex Sans Arabic',
            self::Tajawal => 'Tajawal',
        };
    }

    public function supportsRtl(): bool
    {
        return in_array($this, [self::NotoSansArabic, self::IbmPlexSansArabic, self::Tajawal], true);
    }

    public function supportsLtr(): bool
    {
        return in_array($this, [self::Outfit, self::Inter, self::Manrope, self::Tajawal], true);
    }

    /**
     * Vite input with the @font-face rules of this family.
     */
    public function cssEntry(): string
    {
        return 'resources/css/storefront/fonts/store-'.$this->value.'.css';
    }

    /**
     * @return array<string, string>
     */
    public static function ltrOptions(): array
    {
        return self::optionsWhere(static fn (self $font): bool => $font->supportsLtr());
    }

    /**
     * @return array<string, string>
     */
    public static function rtlOptions(): array
    {
        return self::optionsWhere(static fn (self $font): bool => $font->supportsRtl());
    }

    /**
     * @param  callable(self): bool  $filter
     * @return array<string, string>
     */
    private static function optionsWhere(callable $filter): array
    {
        $options = [];

        foreach (self::cases() as $font) {
            if ($filter($font)) {
                $options[$font->value] = $font->family();
            }
        }

        return $options;
    }

    /**
     * Labels live in the Content module's panel translations: the Storefront module ships no
     * lang directory (decision #45, StorefrontStaticTextsTest).
     */
    public static function translationKey(): string
    {
        return 'content::store_enums.store_font';
    }
}
