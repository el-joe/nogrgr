<?php

namespace App\Modules\Storefront\Support;

/**
 * The 36 brand colours offered in Appearance › Colours & fonts (Figma Vendor Panel
 * 85:107559 "vendor brand palette (36)"): three rows — warm → cool hues, deep tones,
 * greys and navies. A custom #rrggbb is accepted too.
 */
final class BrandPalette
{
    /**
     * @var list<string>
     */
    public const COLORS = [
        '#ff4d00', '#ff5a3c', '#ff7a1a', '#f5a623', '#ffc107', '#c9a800',
        '#2bb24c', '#0ca30c', '#0f9d6b', '#14b8a6', '#0e93b7', '#0b63f6',
        '#2563eb', '#4f46e5', '#6d28d9', '#8a38f5', '#a21caf', '#db2777',
        '#e11d48', '#de1709', '#991b1b', '#7c2d12', '#4a2c22', '#121212',
        '#242424', '#3a3a3a', '#5a5a5a', '#808080', '#a3a3a3', '#c4c4c4',
        '#e5e5e5', '#f5f5f5', '#001537', '#0a2a5e', '#4c1d95', '#5b1170',
    ];
}
