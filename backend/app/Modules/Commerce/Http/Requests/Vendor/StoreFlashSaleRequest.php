<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\DTOs\FlashSaleData;
use App\Modules\Commerce\DTOs\Promotions\ProductRef;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Store flash sale create / edit (VEN-041a, Figma 87:111915): title per language,
 * discount % (1–99), dates, countries (the store's selling countries), products the
 * store sells (typed keys "central:<central id>" / "own:<store product id>", ≥ 1),
 * banner image, active.
 */
class StoreFlashSaleRequest extends FormRequest
{
    /**
     * @var list<ProductRef>|null
     */
    private ?array $refs = null;

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
            'products' => ['required', 'array', 'min:1', 'max:1000', function (string $attribute, mixed $value, Closure $fail): void {
                $keys = array_values(array_filter((array) $value, 'is_string'));
                $this->refs = count($keys) === count((array) $value) ? app(VendorPromotionPageService::class)->resolveRefs($keys) : null;

                if ($this->refs === null) {
                    $fail(__('validation.exists', ['attribute' => __('commerce::flash_sales.fields.products')]));
                }
            }],
            'products.*' => ['string', 'distinct', 'max:40'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::in(array_keys(app(VendorPromotionPageService::class)->countries()))],
            'banner' => ['nullable', 'array', 'max:1'],
            'banner.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_keep' => ['nullable', 'array'],
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
            'products' => __('commerce::flash_sales.fields.products'),
            'country_ids' => __('commerce::flash_sales.fields.countries'),
            'country_ids.*' => __('commerce::flash_sales.fields.countries'),
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
            products: $this->refs ?? [],
            banner: $upload instanceof UploadedFile ? $upload : null,
            removeBanner: $upload === null && ! $keep && $this->route('flashSale') !== null,
        );
    }
}
