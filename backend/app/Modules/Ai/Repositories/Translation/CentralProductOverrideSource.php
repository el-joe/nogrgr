<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;

/**
 * Central products the store sells (decision #65): their text is read from the central
 * product and the translation is written ONLY into the store's `product_overrides` row,
 * in the target locale — central rows are never written and no other locale is copied.
 * Item key = central product id.
 */
class CentralProductOverrideSource implements StoreTranslationSource
{
    public const FIELDS = ['name', 'summary', 'description', 'meta_title', 'meta_description'];

    public function __construct(private readonly ContentTranslationLockRepositoryContract $locks) {}

    public function section(): StoreTranslationSection
    {
        return StoreTranslationSection::ProductOverrides;
    }

    public function keys(string $locale): array
    {
        return StoreProduct::query()
            ->where('source', StoreProductSource::Central->value)
            ->whereNotNull('central_product_id')
            ->orderBy('central_product_id')
            ->distinct()
            ->pluck('central_product_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function items(array $keys, string $locale, array $retranslate = []): array
    {
        $ids = array_map('intval', $keys);
        $overrides = ProductOverride::query()->whereIn('product_id', $ids)->get()->keyBy('product_id');
        $locked = $this->locks->lockedKeys($this->section()->lockType(), $locale);
        $items = [];

        foreach (Product::query()->whereKey($ids)->get() as $product) {
            $key = (string) $product->id;
            $override = $overrides->get($product->id);
            $items[$key] = SourceTexts::item($key, self::FIELDS, $locale, isset($retranslate[$key]),
                static fn (string $field, string $code): string => $code === $locale
                    ? (string) ($override?->getTranslation($field, $code, false) ?: $product->getTranslation($field, $code, false))
                    : (string) $product->getTranslation($field, $code, false),
                static fn (string $field): bool => isset($locked[$key.'|'.$field]),
            );
        }

        return $items;
    }

    public function write(string $key, string $locale, array $translations): void
    {
        if ($translations === []) {
            return;
        }

        $override = ProductOverride::query()->firstOrNew(['product_id' => (int) $key]);

        foreach ($translations as $field => $text) {
            $override->setTranslation($field, $locale, $text);
        }

        $override->save();
    }

    public function models(): array
    {
        return [ProductOverride::class];
    }
}
