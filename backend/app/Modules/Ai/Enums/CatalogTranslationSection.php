<?php

namespace App\Modules\Ai\Enums;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductBadge;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Models\FlashSale;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Storefront\Models\Banner;
use App\Shared\Enums\Concerns\HasEnumHelpers;
use Illuminate\Database\Eloquent\Model;

/**
 * Sections of the admin central catalogue AI translation (ADM-091b): every central
 * AiTranslatable model belongs to exactly one (architecture test).
 */
enum CatalogTranslationSection: string
{
    use HasEnumHelpers;

    case Products = 'products';
    case Categories = 'categories';
    case Attributes = 'attributes';
    case BadgesLabels = 'badges_labels';
    case Promotions = 'promotions';
    case Branches = 'branches';

    /**
     * Item key prefix => central model.
     *
     * @return array<string, class-string<Model&AiTranslatable>>
     */
    public function models(): array
    {
        return match ($this) {
            self::Products => ['product' => Product::class, 'variant' => ProductVariant::class],
            self::Categories => ['category' => Category::class],
            self::Attributes => ['attribute' => Attribute::class, 'option' => AttributeOption::class],
            self::BadgesLabels => ['badge' => ProductBadge::class, 'label' => ProductLabel::class],
            self::Promotions => ['flash_sale' => FlashSale::class, 'banner' => Banner::class],
            self::Branches => ['branch' => Branch::class],
        };
    }
}
