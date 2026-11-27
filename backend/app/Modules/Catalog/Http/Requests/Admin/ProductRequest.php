<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\AssignmentScope;
use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Enums\CountryAvailability;
use App\Modules\Catalog\Enums\DeliveryScope;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Catalog\Support\GalleryMedia;
use App\Modules\Catalog\Support\VariantMatrix;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Product create / edit (ADM-011a–h). Prices are USD decimals (stored as minor units):
 * `price` is required (what tenants pay), `compare_at_price` optional and above it,
 * `cost_price` the platform cost. Variants are keyed by their option combination;
 * cross-row rules (one option per attribute, same attributes everywhere) are enforced
 * by ProductService.
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
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : (is_numeric($product) ? (int) $product : null);
        $money = ['numeric', 'decimal:0,2', 'max:10000000'];

        return [
            ...$locales->rules('name', ['string', 'max:191']),
            ...$locales->rules('summary', ['string', 'max:500'], false),
            ...$locales->rules('description', ['string', 'max:20000'], false),
            ...$locales->rules('meta_title', ['string', 'max:160'], false),
            ...$locales->rules('meta_description', ['string', 'max:320'], false),
            ...$locales->rules('meta_keywords', ['string', 'max:255'], false),
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(Product::class, 'sku')->ignore($productId)],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Product::class, 'slug')->ignore($productId)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price' => ['required', ...$money, 'gt:0'],
            'compare_at_price' => ['nullable', ...$money, 'gt:price'],
            'cost_price' => ['required', ...$money, 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_taxable' => ['required', 'boolean'],
            'requires_shipping' => ['required', 'boolean'],
            'delivery_scope' => ['required', Rule::enum(DeliveryScope::class)],
            'country_mode' => ['required', Rule::enum(CountryAvailability::class)],
            'country_ids' => ['nullable', 'array', 'required_if:country_mode,'.CountryAvailability::Selected->value],
            'country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')->whereNull('deleted_at')],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'distinct', Rule::exists(ProductLabel::class, 'id')->whereNull('deleted_at')],
            'badges' => ['nullable', 'array'],
            'badges.*' => ['string', 'distinct', Rule::in(BadgeType::values())],
            'assignment_scope' => ['required', Rule::enum(AssignmentScope::class)],
            'tenant_ids' => ['nullable', 'array', 'required_if:assignment_scope,'.AssignmentScope::Selected->value],
            'tenant_ids.*' => ['integer', 'distinct', Rule::exists(Tenant::class, 'id')],
            'manage_stock' => ['required', 'boolean'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants' => ['nullable', 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => ['nullable', 'integer', 'min:1'],
            'variants.*.option_ids' => ['required', 'array', 'min:1'],
            'variants.*.option_ids.*' => ['integer', Rule::exists(AttributeOption::class, 'id')->whereNull('deleted_at')],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'variants.*.price' => ['nullable', ...$money, 'gt:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'variants.*.remove_image' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => GalleryMedia::rules(['image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=300,min_height=300']),
            'images_keep' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images_keep.*' => ['integer', 'min:1'],
            'stock' => ['nullable', 'array'],
            'stock.*' => ['array'],
            'stock.*.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = [
            'name', 'summary', 'description', 'meta_title', 'meta_description', 'meta_keywords', 'sku', 'slug', 'status', 'price',
            'compare_at_price', 'cost_price', 'weight_grams', 'delivery_scope', 'country_mode', 'country_ids', 'category_ids',
            'label_ids', 'badges', 'assignment_scope', 'tenant_ids', 'min_stock', 'variants', 'images',
        ];
        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = __('catalog::products.fields.'.$field);
        }

        return $attributes + [
            'variants.*.sku' => __('catalog::products.variants.sku'),
            'variants.*.price' => __('catalog::products.variants.price'),
            'variants.*.weight_grams' => __('catalog::products.variants.weight'),
            'variants.*.image' => __('catalog::products.variants.image'),
            'images.*' => __('catalog::products.fields.images'),
            'stock.*.*' => __('catalog::products.stock.quantity'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $locales = app(CatalogLocales::class);
        $minor = fn (mixed $value): ?int => $value === null || $value === '' ? null : Money::of((string) $value)->minor;
        $variants = [];

        foreach ((array) ($this->validated('variants') ?? []) as $index => $row) {
            $optionIds = array_map('intval', (array) $row['option_ids']);
            $file = $this->file('variants.'.$index.'.image');
            $variants[] = [
                'key' => VariantMatrix::key($optionIds),
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'option_ids' => $optionIds,
                'sku' => isset($row['sku']) && $row['sku'] !== '' ? (string) $row['sku'] : null,
                'price_minor' => $minor($row['price'] ?? null),
                'weight_grams' => isset($row['weight_grams']) && $row['weight_grams'] !== '' ? (int) $row['weight_grams'] : null,
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                'image' => $file instanceof UploadedFile ? $file : null,
                'remove_image' => filter_var($row['remove_image'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        $stock = [];

        foreach ((array) ($this->validated('stock') ?? []) as $branchId => $cells) {
            foreach ((array) $cells as $key => $quantity) {
                if ($quantity !== null && $quantity !== '') {
                    $stock[(int) $branchId][(string) $key] = (int) $quantity;
                }
            }
        }

        $ints = fn (string $key): array => array_values(array_map('intval', (array) ($this->validated($key) ?? [])));

        return [
            'name' => $locales->clean($this->validated('name')),
            'summary' => $locales->clean($this->validated('summary')),
            'description' => $locales->clean($this->validated('description')),
            'meta_title' => $locales->clean($this->validated('meta_title')),
            'meta_description' => $locales->clean($this->validated('meta_description')),
            'meta_keywords' => $locales->clean($this->validated('meta_keywords')),
            'sku' => (string) $this->validated('sku'),
            'slug' => $this->filled('slug') ? (string) $this->validated('slug') : null,
            'status' => (string) $this->validated('status'),
            'price_minor' => (int) $minor($this->validated('price')),
            'compare_at_minor' => $minor($this->validated('compare_at_price')),
            'cost_minor' => (int) $minor($this->validated('cost_price')),
            'weight_grams' => $this->filled('weight_grams') ? (int) $this->validated('weight_grams') : null,
            'is_taxable' => $this->boolean('is_taxable'),
            'requires_shipping' => $this->boolean('requires_shipping'),
            'delivery_scope' => (string) $this->validated('delivery_scope'),
            'country_availability' => (string) $this->validated('country_mode'),
            'country_ids' => $ints('country_ids'),
            'category_ids' => $ints('category_ids'),
            'label_ids' => $ints('label_ids'),
            'badges' => array_values(array_map('strval', (array) ($this->validated('badges') ?? []))),
            'assignment_scope' => (string) $this->validated('assignment_scope'),
            'tenant_ids' => $ints('tenant_ids'),
            'manage_stock' => $this->boolean('manage_stock'),
            'min_stock' => (int) ($this->validated('min_stock') ?? 0),
            'variants' => $variants,
            'keep_media_ids' => $ints('images_keep'),
            'new_images' => array_values(array_filter((array) $this->file('images', []), static fn (mixed $file): bool => $file instanceof UploadedFile)),
            'stock' => $stock,
        ];
    }
}
