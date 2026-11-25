<?php

namespace App\Modules\Commerce\Http\Requests\Admin;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\DTOs\FlashSaleData;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Central flash sale create / edit (ADM-026a, Figma 70:41386): title (ar + en), discount
 * % (1–99), dates (required), countries, products (central, ≥ 1), stores, banner image,
 * active.
 */
class FlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $discount = $this->input('discount_percentage');

        $this->merge([
            'discount_percentage' => is_string($discount) ? trim(str_replace('%', '', $discount)) : $discount,
            'is_active' => $this->boolean('is_active'),
            'remove_banner' => $this->boolean('remove_banner'),
            'store_scope' => $this->input('store_scope', PromotionScope::AllStores->value),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...app(CatalogLocales::class)->rules('title', ['string', 'max:120']),
            'discount_percentage' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99'],
            'dates_from' => ['required', 'date_format:Y-m-d'],
            'dates_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:dates_from'],
            'product_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists(Product::class, 'id')->whereNull('deleted_at')],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'store_scope' => ['required', Rule::enum(PromotionScope::class)],
            'tenant_ids' => ['exclude_unless:store_scope,'.PromotionScope::SelectedStores->value, 'required', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', 'distinct', Rule::exists(Tenant::class, 'id')],
            'banner' => ['nullable', 'array', 'max:1'],
            'banner.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_keep' => ['nullable', 'array'],
            'remove_banner' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'title' => __('commerce::flash_sales.fields.title'),
            'discount_percentage' => __('commerce::flash_sales.fields.discount'),
            'dates_from' => __('commerce::flash_sales.fields.starts'),
            'dates_to' => __('commerce::flash_sales.fields.ends'),
            'product_ids' => __('commerce::flash_sales.fields.products'),
            'country_ids' => __('commerce::flash_sales.fields.countries'),
            'store_scope' => __('commerce::flash_sales.fields.stores'),
            'tenant_ids' => __('commerce::flash_sales.fields.selected_stores'),
            'banner' => __('commerce::flash_sales.fields.banner'),
            'banner.*' => __('commerce::flash_sales.fields.banner'),
        ];

        foreach (app(CatalogLocales::class)->codes() as $code) {
            $attributes['title.'.$code] = __('commerce::flash_sales.fields.title_locale', ['locale' => strtoupper($code)]);
        }

        return $attributes;
    }

    public function toData(): FlashSaleData
    {
        $banner = $this->file('banner');
        $upload = is_array($banner) ? ($banner[0] ?? null) : $banner;
        $keep = (array) $this->input('banner_keep', []) !== [];

        return new FlashSaleData(
            title: app(CatalogLocales::class)->clean((array) $this->validated('title')),
            discountPercentage: (float) $this->validated('discount_percentage'),
            startsAt: CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('dates_from'))?->startOfDay() ?? CarbonImmutable::now(),
            endsAt: CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('dates_to'))?->endOfDay() ?? CarbonImmutable::now(),
            isActive: (bool) $this->validated('is_active'),
            countryIds: array_values(array_map('intval', (array) $this->validated('country_ids', []))),
            productIds: array_values(array_map('intval', (array) $this->validated('product_ids'))),
            storeScope: PromotionScope::from((string) $this->validated('store_scope')),
            tenantIds: array_values(array_map('intval', (array) $this->validated('tenant_ids', []))),
            banner: $upload instanceof UploadedFile ? $upload : null,
            removeBanner: $upload === null && ! $keep && $this->route('flashSale') !== null,
        );
    }
}
