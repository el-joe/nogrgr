<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\GalleryMedia;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin product create / edit (ADM-011, decision #115 = the old controller form):
 * Basics (SKU, slug, status, factory, weight, stock / tax flags), primary image, media
 * gallery, translations (name, label, summary, description, SEO meta), pricing &
 * inventory (base price, optional promotional price below it, cost, stock, low-stock
 * threshold), categories (drill-down, deepest level only), variants (option
 * combination + price / stock / SKU / weight / thumbnail), badges & labels and tenant
 * assignments. Prices are USD decimals stored as minor units. There is no country
 * availability: every product is sold in every country (#115).
 */
class ProductRequest extends FormRequest
{
    public const MAX_VARIANTS = 300;

    public const MAX_IMAGES = 12;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locales = app(CatalogLocales::class);
        $productId = $this->productId();
        $money = ['numeric', 'decimal:0,2', 'max:10000000'];
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=300,min_height=300'];

        return [
            ...$locales->rules('name', ['string', 'max:191']),
            ...$locales->rules('label', ['string', 'max:191'], false),
            ...$locales->rules('summary', ['string', 'max:500'], false),
            ...$locales->rules('description', ['string', 'max:20000'], false),
            ...$locales->rules('meta_title', ['string', 'max:160'], false),
            ...$locales->rules('meta_description', ['string', 'max:320'], false),
            ...$locales->rules('meta_keywords', ['string', 'max:255'], false),
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(Product::class, 'sku')->ignore($productId)],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Product::class, 'slug')->ignore($productId)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'factory' => ['nullable', 'string', 'max:255'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'manage_stock' => ['required', 'boolean'],
            'is_taxable' => ['required', 'boolean'],
            'primary_image' => ['nullable', 'array', 'max:1'],
            'primary_image.*' => $image,
            'primary_image_keep' => ['nullable', 'array', 'max:1'],
            'primary_image_keep.*' => ['integer', 'min:1'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => GalleryMedia::rules($image),
            'images_keep' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images_keep.*' => ['integer', 'min:1'],
            'base_price' => ['required', ...$money, 'gt:0'],
            'sale_price' => ['nullable', ...$money, 'gt:0', 'lt:base_price'],
            'cost_price' => ['nullable', ...$money, 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')->whereNull('deleted_at')],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists(ProductLabel::class, 'id')->whereNull('deleted_at')],
            'badges' => ['nullable', 'array'],
            'badges.*' => ['string', 'distinct', Rule::in(BadgeType::values())],
            'assignment_scope' => ['required', Rule::enum(AssignmentScope::class)],
            'tenant_ids' => ['nullable', 'array', 'required_if:assignment_scope,'.AssignmentScope::Selected->value],
            'tenant_ids.*' => ['integer', 'distinct', Rule::exists(Tenant::class, 'id')],
            'variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => ['nullable', 'integer', 'min:1'],
            'variants.*.option_ids' => ['required', 'array', 'min:1'],
            'variants.*.option_ids.*' => ['integer', Rule::exists(AttributeOption::class, 'id')->whereNull('deleted_at')],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'variants.*.price' => ['nullable', ...$money, 'gt:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'variants.*.remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Only the deepest category level may be added (old controller rule); categories the
     * product already has are kept as they are, so older data still saves.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $ids = array_values(array_filter(array_map('intval', (array) $this->input('category_ids', []))));

            if ($ids === [] || $validator->errors()->has('category_ids*')) {
                return;
            }

            $product = $this->route('product');
            $current = $product instanceof Product ? array_map('intval', $product->categories()->pluck('categories.id')->all()) : [];
            $added = array_values(array_diff($ids, $current));

            if ($added !== [] && Category::query()->whereIn('parent_id', $added)->exists()) {
                $validator->errors()->add('category_ids', __('catalog::products.categories.leaf_only'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = [
            'name', 'label', 'summary', 'description', 'meta_title', 'meta_description', 'meta_keywords', 'sku', 'slug', 'status',
            'factory', 'weight_grams', 'base_price', 'sale_price', 'cost_price', 'stock', 'min_stock', 'category_ids',
            'label_ids', 'badges', 'assignment_scope', 'tenant_ids', 'variants', 'images', 'primary_image',
        ];
        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = __('catalog::products.fields.'.$field);
        }

        return $attributes + [
            'variants.*.sku' => __('catalog::products.variants.sku'),
            'variants.*.price' => __('catalog::products.variants.price'),
            'variants.*.stock' => __('catalog::products.variants.stock'),
            'variants.*.weight_grams' => __('catalog::products.variants.weight'),
            'variants.*.image' => __('catalog::products.variants.image'),
            'images.*' => __('catalog::products.fields.images'),
            'primary_image.*' => __('catalog::products.fields.primary_image'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $locales = app(CatalogLocales::class);
        $minor = fn (mixed $value): ?int => $value === null || $value === '' ? null : Money::of((string) $value)->minor;
        $int = static fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value;
        $variants = [];
        $quantities = [];

        foreach ((array) ($this->validated('variants') ?? []) as $index => $row) {
            $optionIds = array_map('intval', (array) $row['option_ids']);
            $file = $this->file('variants.'.$index.'.image');
            $key = VariantMatrix::key($optionIds);
            $quantities[$key] = (int) ($int($row['stock'] ?? null) ?? 0);
            $variants[] = [
                'key' => $key,
                'id' => $int($row['id'] ?? null),
                'option_ids' => $optionIds,
                'sku' => isset($row['sku']) && $row['sku'] !== '' ? (string) $row['sku'] : null,
                'price_minor' => $minor($row['price'] ?? null),
                'weight_grams' => $int($row['weight_grams'] ?? null),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                'image' => $file instanceof UploadedFile ? $file : null,
                'remove_image' => filter_var($row['remove_image'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        if ($variants === []) {
            $quantities = ['base' => (int) ($int($this->validated('stock')) ?? 0)];
        }

        $base = (int) $minor($this->validated('base_price'));
        $sale = $minor($this->validated('sale_price'));
        $ints = fn (string $key): array => array_values(array_map('intval', (array) ($this->validated($key) ?? [])));
        $files = fn (string $key): array => array_values(array_filter((array) $this->file($key, []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        return [
            'name' => $locales->clean($this->validated('name')),
            'label' => $locales->clean($this->validated('label')),
            'summary' => $locales->clean($this->validated('summary')),
            'description' => $locales->clean($this->validated('description')),
            'meta_title' => $locales->clean($this->validated('meta_title')),
            'meta_description' => $locales->clean($this->validated('meta_description')),
            'meta_keywords' => $locales->clean($this->validated('meta_keywords')),
            'sku' => (string) $this->validated('sku'),
            'slug' => $this->filled('slug') ? (string) $this->validated('slug') : null,
            'status' => (string) $this->validated('status'),
            'factory' => $this->filled('factory') ? trim((string) $this->validated('factory')) : null,
            // Promotional price = what tenants pay while it is set; the base price is then shown struck through.
            'price_minor' => $sale ?? $base,
            'compare_at_minor' => $sale !== null ? $base : null,
            'cost_minor' => $minor($this->validated('cost_price')),
            'weight_grams' => $int($this->validated('weight_grams')),
            'is_taxable' => $this->boolean('is_taxable'),
            'category_ids' => $ints('category_ids'),
            'label_ids' => $ints('label_ids'),
            'badges' => array_values(array_map('strval', (array) ($this->validated('badges') ?? []))),
            'assignment_scope' => (string) $this->validated('assignment_scope'),
            'tenant_ids' => $ints('tenant_ids'),
            'manage_stock' => $this->boolean('manage_stock'),
            'min_stock' => (int) ($this->validated('min_stock') ?? 0),
            'variants' => $variants,
            'primary_keep_id' => $ints('primary_image_keep')[0] ?? null,
            'primary_image' => $files('primary_image')[0] ?? null,
            'keep_media_ids' => $ints('images_keep'),
            'new_images' => $files('images'),
            'quantities' => $quantities,
            'notify_stores' => true,
        ];
    }

    private function productId(): ?int
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product->id : (is_numeric($product) ? (int) $product : null);
    }
}
