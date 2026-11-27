<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyMedia;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Shared\Support\Icon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Central catalogue: categories (tree + thumbnail), attributes + options (old
 * variations), badges (matched to the seeded BadgeType rows), labels, products,
 * variants, their pivots and images (files copied, conversion queued). Money =
 * USD minor units; old country ids in JSON maps are re-keyed to new ids.
 */
class CatalogStep implements LegacyStepHandler
{
    private const CHUNK = 200;

    /** Old label icon names (ProductLabelIcons) that have another name in our icon set. */
    private const LABEL_ICON_ALIASES = ['bolt' => 'zap', 'fire' => 'flame', 'package' => 'box', 'undo' => 'return'];

    public function __construct(private readonly LegacyMedia $media) {}

    public function step(): LegacyStep
    {
        return LegacyStep::Catalog;
    }

    public function run(LegacyContext $context): void
    {
        $this->categories($context);
        $this->attributes($context);
        $this->badges($context);
        $this->labels($context);
        $this->products($context);
    }

    private function categories(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $rows = $source->table('categories')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\Category', $rows->pluck('id')->map('intval')->all());
        $images = $this->files($context, 'App\\Models\\Category', $rows->pluck('id')->all());

        foreach ($rows as $row) {
            $context->report->read('central', 'categories');
            $id = (int) $row->id;
            $name = LegacyTranslations::field($texts, $id, 'name') ?? ['en' => 'Category '.$id];
            $slugSource = $texts[$id]['slug']['en'] ?? $name['en'] ?? reset($name);
            $ownId = $writer->id('category', $id);
            $image = $images[$id][0] ?? null;
            $thumb = $image !== null ? $this->media->copy($context, 'categories', $id, $image->path, Category::THUMBNAIL_DIRECTORY) : null;

            $writer->upsert('category', $id, 'categories', array_filter([
                'slug' => LegacyUnique::value($writer, 'categories', 'slug', Str::slug((string) $slugSource) ?: 'category-'.$id, $ownId, $id),
                'name' => $name,
                'description' => LegacyTranslations::field($texts, $id, 'description'),
                'meta_title' => null,
                'meta_description' => LegacyTranslations::field($texts, $id, 'meta_description'),
                'status' => ($row->status === 'published' || $row->status === 'active' ? RecordStatus::Active : RecordStatus::Inactive)->value,
                'is_featured' => (bool) $row->is_featured,
                'show_in_menu' => true,
                'sort_order' => (int) $row->order_number,
                'thumbnail_path' => $thumb['path'] ?? null,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
            ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, ['description', 'meta_title', 'meta_description', 'deleted_at'], true), ARRAY_FILTER_USE_BOTH));
        }

        // Second pass: parents (they may come later in id order).
        foreach ($rows->whereNotNull('parent_id') as $row) {
            $parent = $writer->id('category', (int) $row->parent_id);

            if ($parent === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'categories', (int) $row->id, 'Parent category missing (orphan, kept as top level)');

                continue;
            }

            if (! $context->dryRun()) {
                $writer->connection()->table('categories')->where('id', $writer->id('category', (int) $row->id))->update(['parent_id' => $parent]);
            }
        }
    }

    private function attributes(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $variations = $source->table('variations')->orderBy('id')->get();
        $options = $source->table('variation_options')->orderBy('id')->get();
        $names = $context->translations()->load('App\\Models\\Variation', $variations->pluck('id')->map('intval')->all());
        $optionNames = $context->translations()->load('App\\Models\\VariationOption', $options->pluck('id')->map('intval')->all());
        $swatched = $options->whereNotNull('swatch')->pluck('variation_id')->map('intval')->unique()->all();

        foreach ($variations as $position => $row) {
            $context->report->read('central', 'attributes');
            $id = (int) $row->id;

            $writer->upsert('attribute', $id, 'attributes', [
                'name' => LegacyTranslations::field($names, $id, 'name') ?? ['en' => 'Attribute '.$id],
                'display_type' => (in_array($id, $swatched, true) ? AttributeDisplayType::Color : AttributeDisplayType::Text)->value,
                'status' => ($row->status === 'active' ? RecordStatus::Active : RecordStatus::Inactive)->value,
                'sort_order' => $position,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
            ]);
        }

        foreach ($options->chunk(500) as $chunk) {
            $writer->transaction(function () use ($chunk, $context, $writer, $optionNames): void {
                foreach ($chunk as $position => $row) {
                    $context->report->read('central', 'attribute_options');
                    $attributeId = $writer->id('attribute', (int) $row->variation_id);

                    if ($attributeId === null) {
                        $context->report->issue(LegacyIssueLevel::Warning, 'central', 'attribute_options', (int) $row->id, 'Option of a missing attribute skipped (orphan)');
                        $context->report->skipped('central', 'attribute_options');

                        continue;
                    }

                    $writer->upsert('attribute_option', (int) $row->id, 'attribute_options', [
                        'attribute_id' => $attributeId,
                        'name' => LegacyTranslations::field($optionNames, (int) $row->id, 'name') ?? ['en' => 'Option '.$row->id],
                        'swatch' => $row->swatch,
                        'is_active' => $row->deleted_at === null,
                        'sort_order' => (int) $position,
                        'created_at' => $row->created_at,
                        'deleted_at' => $row->deleted_at,
                    ]);
                }
            });
        }
    }

    private function badges(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('product_badges')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'badges');
            $type = BadgeType::tryFrom(Str::snake(str_replace('-', '_', (string) $row->text)));

            if ($type === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'badges', (int) $row->id, 'Badge has no new badge type (skipped, products keep no badge)');
                $context->report->skipped('central', 'badges');

                continue;
            }

            $existing = $writer->connection()->table('product_badges')->where('type', $type->value)->value('id');

            if ($existing === null) {
                $writer->upsert('badge', (int) $row->id, 'product_badges', [
                    'type' => $type->value,
                    'name' => ['en' => Str::headline((string) $row->text)],
                    'is_active' => (bool) $row->active,
                    'auto_fill' => 'manual',
                    'auto_fill_limit' => 12,
                    'sort_order' => (int) $row->id,
                ]);

                continue;
            }

            $writer->upsert('badge', (int) $row->id, 'product_badges', ['is_active' => (bool) $row->active], ['type' => $type->value]);
        }
    }

    private function labels(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('product_labels')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'labels');
            $translations = array_filter(LegacyValues::json($row->translations) ?? [], static fn (mixed $value): bool => is_string($value) && $value !== '');

            $writer->upsert('label', (int) $row->id, 'product_labels', [
                'text' => $translations !== [] ? $translations : ['en' => (string) $row->text],
                'icon' => self::labelIcon($row->icon),
                'is_active' => (bool) $row->active,
                'sort_order' => (int) $row->sort_order,
                'created_at' => $row->created_at,
                'deleted_at' => $row->deleted_at,
            ]);
        }
    }

    /**
     * The old label icon as a name of our icon set (aliases mapped); an icon we do not
     * have becomes null, because the label views only render known icons.
     */
    private static function labelIcon(mixed $icon): ?string
    {
        if (! is_string($icon) || $icon === '') {
            return null;
        }

        $name = self::LABEL_ICON_ALIASES[$icon] ?? $icon;

        return Icon::exists($name) ? $name : null;
    }

    private function products(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $morph = (new Product)->getMorphClass();

        $source->table('products')->orderBy('id')->chunkById(self::CHUNK, function (Collection $rows) use ($context, $writer, $source, $morph): void {
            $ids = $rows->pluck('id')->map('intval')->all();
            $texts = $context->translations()->load('App\\Models\\Product', $ids);
            $images = $this->files($context, 'App\\Models\\Product', $ids);
            $categories = $source->table('category_product')->whereIn('product_id', $ids)->orderBy('sort_order')->get()->groupBy('product_id');
            $attributes = $source->table('product_variation')->whereIn('product_id', $ids)->get()->groupBy('product_id');
            $labels = $source->table('product_label_product')->whereIn('product_id', $ids)->get()->groupBy('product_id');
            $badges = $source->table('product_badge_product')->whereIn('product_id', $ids)->get()->groupBy('product_id');
            $countries = $source->getSchemaBuilder()->hasTable('product_country') ? $source->table('product_country')->whereIn('product_id', $ids)->get()->groupBy('product_id') : collect();
            $assigned = $source->table('product_tenant_assignments')->whereIn('product_id', $ids)->pluck('product_id')->map('intval')->unique()->all();
            $variants = $source->table('product_variants')->whereIn('product_id', $ids)->orderBy('position')->get()->groupBy('product_id');
            $variantIds = $variants->flatten()->pluck('id')->map('intval')->all();
            $variantImages = $this->files($context, 'App\\Models\\ProductVariant', $variantIds);
            $variantOptions = $variantIds === [] ? collect() : $source->table('product_variant_option')->whereIn('product_variant_id', $variantIds)->get()->groupBy('product_variant_id');

            $writer->transaction(function () use ($rows, $context, $texts, $images, $categories, $attributes, $labels, $badges, $countries, $assigned, $variants, $variantImages, $variantOptions, $morph): void {
                foreach ($rows as $row) {
                    $context->report->read('central', 'products');
                    $id = (int) $row->id;
                    $productId = $this->product($context, $row, $texts, in_array($id, $assigned, true), $countries->get($id));

                    $this->productPivots($context, $productId, $id, $categories->get($id), $attributes->get($id), $labels->get($id), $badges->get($id));
                    $this->productMedia($context, $productId, $id, $images[$id] ?? [], $morph);

                    foreach ($variants->get($id, collect()) as $variant) {
                        $this->variant($context, $productId, $variant, $variantImages[(int) $variant->id][0] ?? null, $variantOptions->get($variant->id));
                    }
                }
            });
        });
    }

    /**
     * @param  array<int, array<string, array<string, string>>>  $texts
     */
    private function product(LegacyContext $context, object $row, array $texts, bool $assigned, ?Collection $countries): int
    {
        $writer = $context->central;
        $id = (int) $row->id;
        $ownId = $writer->id('product', $id);
        $name = LegacyTranslations::field($texts, $id, 'name') ?? ['en' => (string) $row->sku];

        foreach (['base_price', 'sale_price', 'cost_price'] as $column) {
            if (LegacyValues::losesPrecision($row->{$column})) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'products', $id, "Sub-cent {$column} rounded to cents");
            }
        }

        if ((float) $row->base_price < 0) {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'products', $id, 'Negative base price');
        }

        $countryIds = $countries === null ? null : $this->countryIds($context, $countries->pluck('country_id')->all());

        return $writer->upsert('product', $id, 'products', [
            'slug' => LegacyUnique::value($writer, 'products', 'slug', Str::slug((string) $row->slug) ?: 'product-'.$id, $ownId, $id),
            'sku' => LegacyUnique::value($writer, 'products', 'sku', (string) ($row->sku ?: 'LEGACY-'.$id), $ownId, $id),
            'name' => $name,
            'label' => LegacyTranslations::field($texts, $id, 'label'),
            'summary' => LegacyTranslations::field($texts, $id, 'summary'),
            'description' => LegacyTranslations::field($texts, $id, 'description'),
            'meta_title' => null,
            'meta_keywords' => LegacyTranslations::field($texts, $id, 'meta_keywords'),
            'meta_description' => LegacyTranslations::field($texts, $id, 'meta_description'),
            'status' => (ProductStatus::tryFrom((string) $row->status) ?? ProductStatus::Draft)->value,
            'delivery_scope' => (DeliveryScope::tryFrom((string) $row->delivery_scope) ?? DeliveryScope::AllZones)->value,
            'assignment_scope' => ($assigned ? AssignmentScope::Selected : AssignmentScope::All)->value,
            'base_price' => LegacyValues::minor($row->base_price),
            'sale_price' => LegacyValues::minorOrNull($row->sale_price),
            'cost_price' => LegacyValues::minorOrNull($row->cost_price),
            'stock' => (int) $row->stock,
            'min_stock' => (int) $row->min_stock,
            'manage_stock' => (bool) $row->manage_stock,
            'is_taxable' => (bool) $row->is_taxable,
            'requires_shipping' => (bool) $row->requires_shipping,
            'weight_grams' => $row->weight_grams,
            'fixed_shipping_costs' => $this->countryMap($context, LegacyValues::json($row->fixed_shipping_costs)),
            'allowed_country_ids' => $countryIds,
            'factory' => $row->factory,
            'sold_count' => (int) $row->sold_count,
            'sort_order' => (int) $row->order_number,
            'published_at' => $row->published_at,
            'out_of_stock_at' => $row->out_of_stock_at,
            'external_source' => $row->external_id !== null ? 'neozena' : null,
            'external_id' => $row->external_id,
            'created_at' => $row->created_at,
            'deleted_at' => $row->deleted_at,
        ]);
    }

    private function productPivots(LegacyContext $context, int $productId, int $legacyId, ?Collection $categories, ?Collection $attributes, ?Collection $labels, ?Collection $badges): void
    {
        $writer = $context->central;
        $rows = [];

        foreach ($categories ?? [] as $pivot) {
            $categoryId = $writer->id('category', (int) $pivot->category_id);

            if ($categoryId === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'category_product', $legacyId, 'Product linked to a missing category (link dropped)');

                continue;
            }

            $rows[$categoryId] = ['category_id' => $categoryId, 'product_id' => $productId, 'sort_order' => (int) $pivot->sort_order];
        }

        $writer->syncPivot('category_product', 'product_id', $productId, array_values($rows));
        $rows = [];

        foreach ($attributes ?? [] as $pivot) {
            $attributeId = $writer->id('attribute', (int) $pivot->variation_id);

            if ($attributeId !== null) {
                $rows[$attributeId] = ['product_id' => $productId, 'attribute_id' => $attributeId];
            }
        }

        $writer->syncPivot('product_attribute', 'product_id', $productId, array_values($rows));
        $rows = [];

        foreach ($labels ?? [] as $pivot) {
            $labelId = $writer->id('label', (int) $pivot->product_label_id);

            if ($labelId !== null) {
                $rows[$labelId] = ['product_label_id' => $labelId, 'product_id' => $productId];
            }
        }

        $writer->syncPivot('product_label_product', 'product_id', $productId, array_values($rows));
        $rows = [];

        foreach ($badges ?? [] as $pivot) {
            $badgeId = $writer->id('badge', (int) $pivot->product_badge_id);

            if ($badgeId === null) {
                continue;
            }

            $country = $pivot->country_id !== null ? $writer->id('country', (int) $pivot->country_id) : null;
            $key = $badgeId.'-'.($country ?? 0);
            $rows[$key] = ['product_badge_id' => $badgeId, 'product_id' => $productId, 'country_id' => $country ?? 0, 'sort_order' => (int) $pivot->sort_order, 'created_at' => now(), 'updated_at' => now()];
        }

        $writer->syncPivot('product_badge_product', 'product_id', $productId, array_values($rows));
    }

    /**
     * @param  list<object>  $files
     */
    private function productMedia(LegacyContext $context, int $productId, int $legacyId, array $files, string $morph): void
    {
        $writer = $context->central;
        $position = 0;

        foreach ($files as $file) {
            if ($file->file_type !== 'image' || str_contains((string) $file->path, '-thumb.')) {
                continue;
            }

            $copied = $this->media->copy($context, 'products', $legacyId, (string) $file->path, ProductService::MEDIA_DIRECTORY);

            if ($copied === null) {
                continue;
            }

            $existing = $writer->id('catalog_media', (int) $file->id);
            $mediaId = $writer->upsert('catalog_media', (int) $file->id, 'catalog_media', [
                'mediable_type' => $morph,
                'mediable_id' => $productId,
                'disk' => LegacyMedia::DISK,
                'path' => $copied['path'],
                'mime_type' => $copied['mime'],
                'size' => $copied['size'],
                'width' => $copied['width'],
                'height' => $copied['height'],
                'conversion_status' => MediaConversionStatus::Pending->value,
                'is_primary' => $position === 0,
                'sort_order' => $position++,
            ]);

            if ($existing === null && ! $context->dryRun() && $mediaId > 0) {
                ConvertCatalogMedia::dispatch($mediaId);
            }
        }

        if ($position === 0) {
            $context->report->issue(LegacyIssueLevel::Warning, 'central', 'products', $legacyId, 'Product without images');
        }
    }

    private function variant(LegacyContext $context, int $productId, object $row, ?object $image, ?Collection $options): void
    {
        $writer = $context->central;
        $id = (int) $row->id;
        $ownId = $writer->id('variant', $id);
        $copied = $image !== null ? $this->media->copy($context, 'variants', $id, (string) $image->path, ProductService::MEDIA_DIRECTORY) : null;
        $sku = $row->sku !== null && $row->sku !== '' ? LegacyUnique::value($writer, 'product_variants', 'sku', (string) $row->sku, $ownId, $id) : null;

        $context->report->read('central', 'variants');
        $variantId = $writer->upsert('variant', $id, 'product_variants', [
            'product_id' => $productId,
            'sku' => $sku,
            'title' => $row->title !== null ? ['en' => (string) $row->title] : null,
            'price' => LegacyValues::minorOrNull($row->price),
            'weight_grams' => $row->weight_grams,
            'fixed_shipping_costs' => $this->countryMap($context, LegacyValues::json($row->fixed_shipping_costs)),
            'stock' => (int) $row->stock,
            'position' => (int) $row->position,
            'status' => ($row->status === 'active' ? RecordStatus::Active : RecordStatus::Inactive)->value,
            'image_path' => $copied['path'] ?? null,
            'created_at' => $row->created_at,
            'deleted_at' => $row->deleted_at,
        ]);

        $rows = [];

        foreach ($options ?? [] as $pivot) {
            $optionId = $writer->id('attribute_option', (int) $pivot->variation_option_id);

            if ($optionId === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'variants', $id, 'Variant option missing (link dropped)');

                continue;
            }

            $rows[$optionId] = ['product_variant_id' => $variantId, 'attribute_option_id' => $optionId];
        }

        $writer->syncPivot('product_variant_option', 'product_variant_id', $variantId, array_values($rows));
    }

    /**
     * Old country-keyed fixed-shipping map (USD decimals) → new country ids, minor units (unknown countries dropped).
     *
     * @param  array<mixed>|null  $map
     * @return array<string, mixed>|null
     */
    private function countryMap(LegacyContext $context, ?array $map): ?array
    {
        if ($map === null) {
            return null;
        }

        $out = [];

        foreach ($map as $country => $value) {
            if ($country === 'default') {
                $out['default'] = LegacyValues::minor($value);

                continue;
            }

            $new = $context->central->id('country', (int) $country);

            if ($new !== null) {
                $out[(string) $new] = LegacyValues::minor($value);
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function countryIds(LegacyContext $context, array $ids): array
    {
        return array_values(array_filter(array_map(static fn (mixed $id): ?int => $context->central->id('country', (int) $id), $ids)));
    }

    /**
     * Old `files` rows of one model type, grouped by owner id, in display order.
     *
     * @param  list<mixed>  $ids
     * @return array<int, list<object>>
     */
    private function files(LegacyContext $context, string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];
        $rows = $context->source->central()->table('files')->where('model_type', $type)->whereIn('model_id', $ids)->orderBy('sort_order')->orderBy('id')->get();

        foreach ($rows as $row) {
            $out[(int) $row->model_id][] = $row;
        }

        return $out;
    }
}
