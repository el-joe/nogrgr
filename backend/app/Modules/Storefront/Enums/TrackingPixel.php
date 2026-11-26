<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Marketing pixels a store can switch on (Settings › Tracking pixels, VEN-083). The
 * storefront loads them only after the shopper accepts tracking cookies, through the
 * one allow-listed loader resources/js/storefront/core/tracking.js (decision #91).
 */
enum TrackingPixel: string
{
    use HasEnumHelpers;

    case Meta = 'meta';
    case Tiktok = 'tiktok';
    case Snap = 'snap';
    case Ga4 = 'ga4';

    /**
     * Shape of the id the network issues.
     */
    public function pattern(): string
    {
        return match ($this) {
            self::Meta => '/^\d{10,20}$/',
            self::Tiktok => '/^[A-Z0-9]{10,30}$/',
            self::Snap => '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            self::Ga4 => '/^G-[A-Z0-9]{4,16}$/',
        };
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::Meta => '102233445566778',
            self::Tiktok => 'CK3BQ5RC77U2EXAMPLE',
            self::Snap => '8f9e2b1c-3d4a-4b5c-9d8e-1a2b3c4d5e6f',
            self::Ga4 => 'G-MEGZ1234',
        };
    }

    /**
     * Normalises what the vendor typed (spaces removed, case of the network).
     */
    public function normalize(string $id): string
    {
        $id = (string) preg_replace('/\s+/', '', $id);

        return match ($this) {
            self::Snap => strtolower($id),
            self::Tiktok, self::Ga4 => strtoupper($id),
            self::Meta => $id,
        };
    }

    /**
     * Labels live in the Content module's panel translations: the Storefront module ships no
     * lang directory (decision #45, StorefrontStaticTextsTest).
     */
    public static function translationKey(): string
    {
        return 'content::store_enums.tracking_pixel';
    }
}
