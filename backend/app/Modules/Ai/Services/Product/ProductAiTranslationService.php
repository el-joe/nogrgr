<?php

namespace App\Modules\Ai\Services\Product;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Translation\GuardedTranslator;
use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Models\VariantOverride;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TenantLanguage;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\LanguageRepositoryContract;
use App\Modules\Localization\Repositories\Contracts\TenantLanguageRepositoryContract;
use App\Shared\Services\BaseService;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * VEN-012b "Translate with AI" of one store product and its variants into chosen store
 * languages (custom provider, decisions #43 / #44 a). Preview first (nothing saved), then
 * apply the previewed — possibly edited — texts.
 *
 * Items: "product" and "variant:<id>". Source text per field = English, else Arabic
 * (a central product's English / Arabic store override wins over the central text).
 * Writes: own products into their own row; central products ONLY into the store's
 * product_overrides / variant_overrides in the target locale (decision #65, central rows
 * never written). Never translated: fields hand-locked for that locale
 * (content_translation_locks) and, for central products, ar / en fields the central
 * product already has (central content in the core languages changes through edit
 * requests, VEN-013).
 */
class ProductAiTranslationService extends BaseService
{
    public const CORE = ['en', 'ar'];

    public const PRODUCT_KEY = 'product';

    public const CENTRAL_FIELDS = ['name', 'summary', 'description', 'meta_title', 'meta_description'];

    public const OWN_FIELDS = ['name', 'label', 'summary', 'description', 'meta_title', 'meta_description'];

    /**
     * Override rows of the current apply() (one row per item, saved once).
     *
     * @var array<string, ProductOverride|VariantOverride>
     */
    private array $writeCache = [];

    public function __construct(
        private readonly TenantLanguageRepositoryContract $tenantLanguages,
        private readonly LanguageRepositoryContract $languages,
        private readonly ContentTranslationLockRepositoryContract $locks,
        private readonly GuardedTranslator $translator,
    ) {}

    /**
     * Enabled store languages, code => name (store order).
     *
     * @return array<string, string>
     */
    public function storeLanguages(): array
    {
        $result = [];

        foreach ($this->tenantLanguages->ordered() as $language) {
            /** @var TenantLanguage $language */
            if (! $language->is_enabled) {
                continue;
            }

            $row = $this->languages->findByCode($language->language_code);
            $result[$language->language_code] = $row instanceof Language ? (string) $row->name : strtoupper($language->language_code);
        }

        return $result;
    }

    /**
     * Current texts per store language for the Content tab: locale => item key => field
     * => ['value' => shown text, 'overridden' => written for this store, 'translatable' => AI may write it].
     *
     * @return array<string, array<string, array<string, array{value: string, overridden: bool, translatable: bool}>>>
     */
    public function content(StoreProduct $product): array
    {
        $result = [];

        foreach (array_keys($this->storeLanguages()) as $locale) {
            $targets = $this->targets($product, $locale);

            foreach ($this->records($product) as $key => [$fields, $read, $own]) {
                foreach ($fields as $field) {
                    $ownValue = (string) $own($field, $locale);
                    $value = $ownValue !== '' ? $ownValue : (string) $read($field, $locale);

                    $result[$locale][$key][$field] = [
                        'value' => $value,
                        'overridden' => $ownValue !== '' && ! $product->isOwn(),
                        'translatable' => isset($targets[$key][$field]),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Translate (nothing saved): locale => item key => field => text.
     *
     * @param  list<string>  $locales
     * @return array<string, array<string, array<string, string>>>
     *
     * @throws DomainException when no language / nothing to translate / the provider fails
     */
    public function preview(StoreProduct $product, array $locales): array
    {
        $this->guardLocales($locales);
        $client = app(TranslationClient::class);
        $context = new AiUsageContext(AiFeature::ProductTranslation, tenancy()->initialized ? (int) tenant()?->getTenantKey() : null, 'store_product:'.$product->id);
        $glossary = $this->glossary($product);
        $result = [];

        foreach ($locales as $locale) {
            $items = [];

            foreach ($this->targets($product, $locale) as $key => $fields) {
                $items[$key] = new TranslationItemData((string) $key, array_map(static fn (array $source): string => $source[1], $fields), array_map(static fn (array $source): string => $source[0], $fields), '');
            }

            if ($items === []) {
                continue;
            }

            try {
                $translated = $this->translator->translate($client, $items, $locale, $glossary, $context);
            } catch (AiRequestException $exception) {
                throw new DomainException($exception->getMessage(), 0, $exception);
            }

            foreach ($translated as $key => $fields) {
                if ($fields === null) {
                    throw new DomainException((string) __('ai::product_translation.errors.placeholders'));
                }

                $result[$locale][$key] = $fields;
            }
        }

        if ($result === []) {
            throw new DomainException((string) __('ai::product_translation.errors.nothing'));
        }

        return $result;
    }

    /**
     * Save previewed texts; fields outside the translatable set are ignored.
     *
     * @param  array<string, array<string, array<string, string|null>>>  $translations  locale => item key => field => text
     * @return int fields written
     */
    public function apply(StoreProduct $product, array $translations): int
    {
        $this->guardLocales(array_map('strval', array_keys($translations)));
        $this->writeCache = [];
        $written = 0;
        $dirty = [];

        foreach ($translations as $locale => $items) {
            $targets = $this->targets($product, (string) $locale);

            foreach ($items as $key => $fields) {
                foreach ($fields as $field => $text) {
                    $text = trim((string) $text);

                    if ($text === '' || ! isset($targets[$key][$field])) {
                        continue;
                    }

                    $record = $this->writable($product, (string) $key);

                    if ($record === null) {
                        continue;
                    }

                    $record->setTranslation((string) $field, (string) $locale, $text);
                    $dirty[spl_object_id($record)] = $record;
                    $written++;
                }
            }
        }

        foreach ($dirty as $record) {
            $record->save();
        }

        if ($written > 0 && tenancy()->initialized) {
            StoreProductsChanged::dispatch((int) tenant()?->getTenantKey(), StoreProductChange::Updated, [$product->id], $product->isOwn() ? [] : [(int) $product->central_product_id]);
        }

        return $written;
    }

    /**
     * Fields AI may write in one locale: item key => field => [source locale, source text].
     *
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    public function targets(StoreProduct $product, string $locale): array
    {
        $own = $product->isOwn();
        $productLocks = $this->locks->lockedKeys($own ? StoreTranslationSection::Products->lockType() : StoreTranslationSection::ProductOverrides->lockType(), $locale);
        $variantLocks = $this->locks->lockedKeys($own ? StoreTranslationSection::Variants->lockType() : StoreTranslationSection::VariantOverrides->lockType(), $locale);
        $targets = [];

        foreach ($this->records($product) as $key => [$fields, $read, $overrideRead, $lockId]) {
            $locks = $key === self::PRODUCT_KEY ? $productLocks : $variantLocks;

            foreach ($fields as $field) {
                $source = null;

                foreach (self::CORE as $code) {
                    $text = (string) ($overrideRead($field, $code) ?: $read($field, $code));

                    if (trim($text) !== '') {
                        $source = [$code, $text];

                        break;
                    }
                }

                if ($source === null || $source[0] === $locale || isset($locks[$lockId.'|'.$field])) {
                    continue;
                }

                if (! $own && in_array($locale, self::CORE, true) && trim((string) $read($field, $locale)) !== '') {
                    continue;
                }

                $targets[$key][$field] = $source;
            }
        }

        return $targets;
    }

    /**
     * item key => [fields, read(field, locale) of the base row, read of the store override
     * (own products: same as base), lock record id].
     *
     * @return array<string, array{0: list<string>, 1: Closure(string, string): string, 2: Closure(string, string): string, 3: int}>
     */
    private function records(StoreProduct $product): array
    {
        if ($product->isOwn()) {
            $records = [self::PRODUCT_KEY => $this->ownRecord($product, self::OWN_FIELDS)];

            foreach ($product->variants as $variant) {
                /** @var StoreProductVariant $variant */
                $records['variant:'.$variant->id] = $this->ownRecord($variant, ['title']);
            }

            return $records;
        }

        $central = $product->centralProduct;

        if (! $central instanceof Product) {
            return [];
        }

        $override = ProductOverride::query()->where('product_id', $central->id)->first();
        $records = [self::PRODUCT_KEY => [
            self::CENTRAL_FIELDS,
            static fn (string $field, string $locale): string => (string) $central->getTranslation($field, $locale, false),
            static fn (string $field, string $locale): string => (string) $override?->getTranslation($field, $locale, false),
            (int) $central->id,
        ]];
        $variantOverrides = VariantOverride::query()->where('product_id', $central->id)->get()->keyBy('variant_id');

        foreach ($central->variants as $variant) {
            /** @var ProductVariant $variant */
            $variantOverride = $variantOverrides->get($variant->id);
            $records['variant:'.$variant->id] = [
                ['title'],
                static fn (string $field, string $locale): string => (string) $variant->getTranslation($field, $locale, false),
                static fn (string $field, string $locale): string => (string) $variantOverride?->getTranslation($field, $locale, false),
                (int) $variant->id,
            ];
        }

        return $records;
    }

    /**
     * @param  list<string>  $fields
     * @return array{0: list<string>, 1: Closure(string, string): string, 2: Closure(string, string): string, 3: int}
     */
    private function ownRecord(StoreProduct|StoreProductVariant $model, array $fields): array
    {
        $read = static fn (string $field, string $locale): string => (string) $model->getTranslation($field, $locale, false);

        return [$fields, $read, static fn (): string => '', (int) $model->id];
    }

    /**
     * The row a translation of one item is written to (central: the store override row).
     */
    private function writable(StoreProduct $product, string $key): StoreProduct|StoreProductVariant|ProductOverride|VariantOverride|null
    {
        $id = $key === self::PRODUCT_KEY ? null : (int) substr($key, strlen('variant:'));

        if ($product->isOwn()) {
            return $id === null ? $product : $product->variants->firstWhere('id', $id);
        }

        $centralId = (int) $product->central_product_id;

        if ($id === null) {
            return $this->writeCache['p'.$centralId] ??= ProductOverride::query()->firstOrNew(['product_id' => $centralId]);
        }

        $variant = $product->centralProduct?->variants->firstWhere('id', $id);

        if (! $variant instanceof Model) {
            return null;
        }

        return $this->writeCache['v'.$id] ??= VariantOverride::query()->firstOrNew(['variant_id' => $id], ['product_id' => $centralId]);
    }

    /**
     * @param  list<string>  $locales
     */
    private function guardLocales(array $locales): void
    {
        $store = $this->storeLanguages();

        if ($locales === []) {
            throw new DomainException((string) __('ai::product_translation.errors.no_language'));
        }

        foreach ($locales as $locale) {
            if (! isset($store[$locale])) {
                throw new DomainException((string) __('ai::product_translation.errors.unknown_language', ['language' => $locale]));
            }
        }
    }

    /**
     * Store name and SKU are never translated.
     *
     * @return array<string, string>
     */
    private function glossary(StoreProduct $product): array
    {
        $glossary = [];
        $name = tenancy()->initialized ? (string) tenant()?->getAttribute('name') : '';

        if ($name !== '') {
            $glossary[$name] = '';
        }

        $sku = $product->isOwn() ? $product->sku : $product->centralProduct?->sku;

        if (is_string($sku) && $sku !== '') {
            $glossary[$sku] = '';
        }

        return $glossary;
    }
}
