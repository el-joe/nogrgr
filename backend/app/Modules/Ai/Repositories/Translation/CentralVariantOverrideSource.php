<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\VariantOverride;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;

/**
 * Variants of the central products the store sells: title read from central, written
 * only into the store's `variant_overrides` row in the target locale (decision #65).
 * Item key = central variant id.
 */
class CentralVariantOverrideSource implements StoreTranslationSource
{
    public const FIELDS = ['title'];

    public function __construct(private readonly ContentTranslationLockRepositoryContract $locks) {}

    public function section(): StoreTranslationSection
    {
        return StoreTranslationSection::VariantOverrides;
    }

    public function keys(string $locale): array
    {
        $products = StoreProduct::query()
            ->where('source', StoreProductSource::Central->value)
            ->whereNotNull('central_product_id')
            ->pluck('central_product_id')
            ->all();

        if ($products === []) {
            return [];
        }

        return ProductVariant::query()->whereIn('product_id', $products)->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function items(array $keys, string $locale, array $retranslate = []): array
    {
        $ids = array_map('intval', $keys);
        $overrides = VariantOverride::query()->whereIn('variant_id', $ids)->get()->keyBy('variant_id');
        $locked = $this->locks->lockedKeys($this->section()->lockType(), $locale);
        $items = [];

        foreach (ProductVariant::query()->whereKey($ids)->get() as $variant) {
            $key = (string) $variant->id;
            $override = $overrides->get($variant->id);
            $items[$key] = SourceTexts::item($key, self::FIELDS, $locale, isset($retranslate[$key]),
                static fn (string $field, string $code): string => $code === $locale
                    ? (string) ($override?->getTranslation($field, $code, false) ?: $variant->getTranslation($field, $code, false))
                    : (string) $variant->getTranslation($field, $code, false),
                static fn (string $field): bool => isset($locked[$key.'|'.$field]),
            );
        }

        return $items;
    }

    public function write(string $key, string $locale, array $translations): void
    {
        $variant = $translations === [] ? null : ProductVariant::query()->find((int) $key);

        if ($variant === null) {
            return;
        }

        $override = VariantOverride::query()->firstOrNew(['variant_id' => $variant->id], ['product_id' => $variant->product_id]);

        foreach ($translations as $field => $text) {
            $override->setTranslation($field, $locale, $text);
        }

        $override->save();
    }

    public function models(): array
    {
        return [VariantOverride::class];
    }
}
