<?php

namespace App\Modules\Ai\Enums;

use App\Modules\Localization\Enums\StoreContentType;
use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Sections of the paid full-store translation run (decision #44), in run order. Each
 * section is one StoreTranslationSource; its items are tracked per locale in the
 * tenant `store_translation_items` table (resume, "retry failed only", source delta).
 */
enum StoreTranslationSection: string
{
    use HasEnumHelpers;

    case Categories = 'categories';
    case Products = 'products';
    case Variants = 'variants';
    case ProductOverrides = 'product_overrides';
    case VariantOverrides = 'variant_overrides';
    case FlashSales = 'flash_sales';
    case Banners = 'banners';
    case MenuLinks = 'menu_links';
    case Pages = 'pages';
    case EmailTemplates = 'email_templates';
    case StoreSettings = 'store_settings';
    case StaticTexts = 'static_texts';

    /**
     * `content_translation_locks.content_type` of the section: the VEN-075 content types
     * keep their StoreContentType value, every other section uses its own value.
     */
    public function lockType(): string
    {
        return match ($this) {
            self::Categories => StoreContentType::Category->value,
            self::Products => StoreContentType::Product->value,
            self::FlashSales => StoreContentType::FlashSale->value,
            default => $this->value,
        };
    }
}
