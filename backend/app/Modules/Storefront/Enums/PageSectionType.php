<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Sections a store page can be built from (VEN-032 / VEN-033, decision #117 b). Each has a
 * settings schema (ThemeSectionLibrary::pageSchema) and one storefront Blade component
 * (x-sf::page-section.<type>).
 */
enum PageSectionType: string
{
    use HasEnumHelpers;

    case RichText = 'rich_text';
    case Banner = 'banner';
    case ProductRail = 'product_rail';
    case CategoryGrid = 'category_grid';
    case Faq = 'faq';
    case ImageText = 'image_text';

    public function icon(): string
    {
        return match ($this) {
            self::RichText => 'file',
            self::Banner => 'image',
            self::ProductRail => 'box',
            self::CategoryGrid => 'grid',
            self::Faq => 'info',
            self::ImageText => 'layers',
        };
    }

    public function component(): string
    {
        return 'sf::page-section.'.str_replace('_', '-', $this->value);
    }

    public static function translationKey(): string
    {
        return 'content::page_builder.page_types';
    }
}
