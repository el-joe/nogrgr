<?php

namespace App\Modules\Localization\Enums;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Shared\Enums\Concerns\HasEnumHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Store-owned translatable content the vendor can translate by hand (VEN-075, tenant
 * DB). Central catalogue content and central-product overrides are not listed here:
 * those change only through edit requests (decision #65).
 */
enum StoreContentType: string
{
    use HasEnumHelpers;

    case Category = 'category';
    case Product = 'product';
    case FlashSale = 'flash_sale';

    /**
     * @return class-string<Model&AiTranslatable>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Category => StoreCategory::class,
            self::Product => StoreProduct::class,
            self::FlashSale => StoreFlashSale::class,
        };
    }

    /**
     * @return Builder<Model&AiTranslatable>
     */
    public function query(): Builder
    {
        $class = $this->modelClass();
        $query = $class::query();

        return $this === self::Product ? $query->where('source', StoreProductSource::Own->value) : $query;
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        $class = $this->modelClass();

        return (new $class)->aiTranslatableFields();
    }

    /**
     * Field shown as the record's name in the list.
     */
    public function titleField(): string
    {
        return $this === self::FlashSale ? 'title' : 'name';
    }

    /**
     * Long fields are edited in a textarea.
     */
    public function isLong(string $field): bool
    {
        return in_array($field, ['description', 'summary', 'meta_description', 'meta_keywords'], true);
    }
}
