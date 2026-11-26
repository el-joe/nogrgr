<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Commerce\Enums\PromotionScope;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\DTOs\BannerInputData;
use App\Modules\Storefront\Enums\BannerPlacement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Central banner create / edit (ADM-085a, Figma 78:82810): per-language title, subtitle
 * and button text; image (required on create) + optional mobile image; link (a path on
 * the store or an https URL); optional date range; countries; placement; stores; active.
 */
class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $link = $this->input('link_url');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'store_scope' => $this->input('store_scope', PromotionScope::AllStores->value),
            'placement' => $this->input('placement', BannerPlacement::HomeHero->value),
            'link_url' => is_string($link) && trim($link) !== '' ? trim($link) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locales = app(CatalogLocales::class);
        $creating = $this->route('banner') === null;

        return [
            ...$locales->rules('title', ['string', 'max:120']),
            'subtitle' => ['nullable', 'array'],
            'subtitle.*' => ['nullable', 'string', 'max:160'],
            'cta_label' => ['nullable', 'array'],
            'cta_label.*' => ['nullable', 'string', 'max:40'],
            'placement' => ['required', Rule::enum(BannerPlacement::class)],
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(/[^\s]*|https?://[^\s]+)$#i'],
            'dates_from' => ['nullable', 'date_format:Y-m-d', 'required_with:dates_to'],
            'dates_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dates_from'],
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'store_scope' => ['required', Rule::enum(PromotionScope::class)],
            'tenant_ids' => ['exclude_unless:store_scope,'.PromotionScope::SelectedStores->value, 'required', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', 'distinct', Rule::exists(Tenant::class, 'id')],
            'image' => [$creating ? 'required' : 'nullable', 'array', 'max:1'],
            'image.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'image_keep' => ['nullable', 'array'],
            'mobile_image' => ['nullable', 'array', 'max:1'],
            'mobile_image.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096'],
            'mobile_image_keep' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'title' => __('engagement::banners.fields.title'),
            'placement' => __('engagement::banners.fields.placement'),
            'link_url' => __('engagement::banners.fields.link'),
            'dates_from' => __('engagement::banners.fields.starts'),
            'dates_to' => __('engagement::banners.fields.ends'),
            'country_ids' => __('engagement::banners.fields.countries'),
            'store_scope' => __('engagement::banners.fields.stores'),
            'tenant_ids' => __('engagement::banners.fields.selected_stores'),
            'image' => __('engagement::banners.fields.image'),
            'image.*' => __('engagement::banners.fields.image'),
            'mobile_image' => __('engagement::banners.fields.mobile_image'),
            'mobile_image.*' => __('engagement::banners.fields.mobile_image'),
        ];

        foreach (app(CatalogLocales::class)->codes() as $code) {
            $attributes['title.'.$code] = __('engagement::banners.fields.title_locale', ['locale' => strtoupper($code)]);
            $attributes['subtitle.'.$code] = __('engagement::banners.fields.subtitle');
            $attributes['cta_label.'.$code] = __('engagement::banners.fields.cta_label');
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['link_url.regex' => __('engagement::banners.validation.link')];
    }

    public function toData(): BannerInputData
    {
        $locales = app(CatalogLocales::class);
        $from = $this->validated('dates_from');
        $to = $this->validated('dates_to');
        $mobile = self::upload($this->file('mobile_image'));

        return new BannerInputData(
            placement: BannerPlacement::from((string) $this->validated('placement')),
            title: $locales->clean((array) $this->validated('title')),
            subtitle: $locales->clean((array) $this->validated('subtitle', [])),
            ctaLabel: $locales->clean((array) $this->validated('cta_label', [])),
            linkUrl: $this->validated('link_url'),
            isActive: (bool) $this->validated('is_active'),
            startsAt: is_string($from) ? CarbonImmutable::createFromFormat('Y-m-d', $from)?->startOfDay() : null,
            endsAt: is_string($to) ? CarbonImmutable::createFromFormat('Y-m-d', $to)?->endOfDay() : null,
            countryIds: array_values(array_map('intval', (array) $this->validated('country_ids', []))),
            storeScope: PromotionScope::from((string) $this->validated('store_scope')),
            tenantIds: array_values(array_map('intval', (array) $this->validated('tenant_ids', []))),
            image: self::upload($this->file('image')),
            mobileImage: $mobile,
            removeMobileImage: $mobile === null && (array) $this->input('mobile_image_keep', []) === [],
        );
    }

    private static function upload(mixed $files): ?UploadedFile
    {
        $file = is_array($files) ? ($files[0] ?? null) : $files;

        return $file instanceof UploadedFile ? $file : null;
    }
}
