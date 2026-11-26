{{--
    Own product create / edit (VEN-016c, Tenant Panel Layout 12:17411, mobile 12:22819):
    Basics (SKU, slug, weight, category, countries, localized name / label / summary /
    description, flags), Pricing (base, sale, cost USD; stock without variants), Variants
    (title per language, SKU, price, sale price, stock) and the gallery (first image =
    primary). "Translate with AI" (VEN-012b, edit only) opens ai::vendor.product-translation.modal.
    Data: $product (StoreProduct, new or own), $categoryOptions, $storeCategoryOptions, $countries (store selling countries).
--}}
@php
    $editing = $product->exists;
    $locales = \App\Shared\Support\Locale::enabled();
    $fallback = (string) config('app.fallback_locale', 'en');
    $translation = fn (string $field, string $locale) => $editing ? $product->getTranslation($field, $locale, false) : null;
    $selectedCountries = $product->country_ids;
    $allCountries = $editing ? $selectedCountries === null : false;
    $images = $editing ? $product->media->map(fn ($media) => ['id' => $media->id, 'url' => \App\Modules\Catalog\Services\Vendor\OwnProductService::imageUrl($media)])->all() : [];
    $variants = $editing ? $product->variants : collect();
    $money = fn ($value) => $value?->toDecimal();
@endphp
<x-vendor::layout :title="$editing ? __('catalog::vendor.own.edit_title') : __('catalog::vendor.own.create_title')" page="vendor-catalog">
    <form id="own-product-form" method="POST" action="{{ $editing ? vendor_route('catalog.own-products.update', ['product' => $product->id]) : vendor_route('catalog.own-products.store') }}" enctype="multipart/form-data" class="grid gap-6" data-ajax data-own-product-form novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <header class="flex flex-wrap items-start justify-between gap-4" data-reveal="fade-up">
            <div class="grid gap-1">
                <h1 class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ $editing ? __('catalog::vendor.own.edit_title') : __('catalog::vendor.own.create_title') }}</h1>
                <p class="text-sm text-ink-muted">{{ __('catalog::vendor.own.form_subtitle') }}</p>
            </div>
            <div class="flex w-full flex-wrap gap-3 sm:w-auto">
                <a href="{{ vendor_route('catalog.own-products.index') }}" class="vnd-cta vnd-cta-outline flex-1 sm:w-[183px] sm:flex-none"><x-ui::icon name="chevron-left" :size="20" data-flip />{{ __('catalog::vendor.own.back') }}</a>
                @if ($editing)
                    @include('ai::vendor.product-translation.button', ['product' => $product])
                    @include('ai::vendor.product-tools.buttons', ['product' => $product, 'style' => 'cta'])
                @endif
                @if ($editing && auth('vendor')->user()?->can('flash-sales.manage'))
                    <button type="button" class="vnd-cta vnd-cta-outline flex-1 sm:flex-none sm:px-5" data-remote-modal="flash-sale-attach-modal" data-url="{{ vendor_route('marketing.flash-sales.available-for-product', ['product' => $product->id]) }}"><x-ui::icon name="zap" :size="20" />{{ __('commerce::vendor_promotions.flash_sales.attach.title') }}</button>
                @endif
                <button type="submit" class="vnd-cta vnd-cta-primary flex-1 sm:w-[183px] sm:flex-none">{{ __('catalog::vendor.own.save') }}</button>
            </div>
        </header>

        <section class="grid gap-5 rounded-[24px] bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.own.basics') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('catalog::vendor.own.basics_hint') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui::input name="sku" :label="__('catalog::vendor.own.fields.sku')" :value="$product->sku" dir="ltr" maxlength="64" />
                <x-ui::input name="slug" :label="__('catalog::vendor.own.fields.slug')" :value="$product->slug" :helper="__('catalog::vendor.own.slug_hint')" dir="ltr" />
                <x-ui::input name="weight_grams" type="number" min="0" :label="__('catalog::vendor.own.fields.weight')" :value="$product->weight_grams ?? 0" required dir="ltr" inputmode="numeric" />
                <x-ui::select name="category_ids" multiple :label="__('catalog::vendor.own.fields.category')" :options="$categoryOptions" :selected="$product->category_ids ?? []" required />
            </div>
            @if ($storeCategoryOptions !== [])
                <x-ui::select name="store_category_ids" multiple :label="__('catalog::vendor.edit.your_store_categories')" :options="$storeCategoryOptions" :selected="array_map('intval', $product->store_category_ids ?? [])" :placeholder="__('catalog::vendor.edit.pick_store_categories')" />
            @endif

            <fieldset class="grid gap-3" data-country-picker>
                <legend class="mb-2 text-sm font-medium text-ink">{{ __('catalog::vendor.own.fields.countries') }}</legend>
                @if ($countries->isEmpty())
                    <p class="rounded-control bg-warning-bg p-3 text-sm text-warning">{{ __('catalog::vendor.products.no_countries') }}</p>
                @else
                    <x-ui::checkbox name="all_countries" :label="__('catalog::vendor.opportunities.all_countries')" :checked="$allCountries" data-countries-all />
                    <div class="ui-control min-h-10">
                        <x-ui::icon name="search" :size="18" class="text-ink-muted" />
                        <label for="own-country-search" class="sr-only">{{ __('catalog::vendor.opportunities.search_countries') }}</label>
                        <input id="own-country-search" type="search" class="h-9" placeholder="{{ __('catalog::vendor.opportunities.search_countries') }}" data-markets-search autocomplete="off">
                    </div>
                    <ul class="grid max-h-[180px] gap-1 overflow-y-auto rounded-card bg-subtle p-3 sm:grid-cols-2" data-markets-list>
                        @foreach ($countries as $country)
                            <li data-market-option data-name="{{ \Illuminate\Support\Str::lower($country->name.' '.$country->iso2) }}">
                                <x-ui::checkbox name="country_ids[]" :value="$country->id" :label="trim($country->flag.' '.$country->name)" :checked="in_array($country->id, $selectedCountries ?? [], true)" />
                            </li>
                        @endforeach
                        <li data-markets-none hidden class="py-2 text-sm text-ink-muted">{{ __('catalog::vendor.opportunities.no_country_match') }}</li>
                    </ul>
                    <p class="text-xs text-danger" data-error-for="country_ids"></p>
                @endif
            </fieldset>

            <x-ui::locale-tabs id="own-i18n" :active="$fallback">
                @foreach ($locales as $code => $language)
                    <x-ui::locale-pane parent="own-i18n" :locale="$code" :active="$code === $fallback">
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui::input :name="'name['.$code.']'" :label="__('catalog::vendor.own.fields.name')" :value="$translation('name', $code)" :required="$code === $fallback" :dir="$language['dir'] ?? 'ltr'" maxlength="255" />
                            <x-ui::input :name="'label['.$code.']'" :label="__('catalog::vendor.own.fields.label')" :value="$translation('label', $code)" :helper="__('catalog::vendor.own.label_hint')" :dir="$language['dir'] ?? 'ltr'" />
                            <x-ui::input :name="'summary['.$code.']'" :label="__('catalog::vendor.own.fields.summary')" :value="$translation('summary', $code)" :required="$code === $fallback" :dir="$language['dir'] ?? 'ltr'" maxlength="500" />
                        </div>
                        <x-ui::textarea :name="'description['.$code.']'" :label="__('catalog::vendor.own.fields.description')" :value="$translation('description', $code)" :required="$code === $fallback" :rows="5" class="mt-4" />
                    </x-ui::locale-pane>
                @endforeach
            </x-ui::locale-tabs>

            <div class="flex flex-wrap gap-x-10 gap-y-3">
                <x-ui::checkbox name="is_active" :label="__('catalog::vendor.own.fields.active')" :checked="$product->is_active ?? true" unchecked-value="0" />
                <x-ui::checkbox name="is_featured" :label="__('catalog::vendor.products.featured')" :checked="(bool) $product->is_featured" unchecked-value="0" />
                <x-ui::checkbox name="manage_stock" :label="__('catalog::vendor.own.fields.manage_stock')" :checked="$product->manage_stock ?? true" unchecked-value="0" />
                <x-ui::checkbox name="is_taxable" :label="__('catalog::vendor.own.fields.taxable')" :checked="$product->is_taxable ?? true" unchecked-value="0" />
            </div>
        </section>

        <section class="grid gap-5 rounded-[24px] bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.own.pricing') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('catalog::vendor.own.pricing_hint') }}</p>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <x-ui::input name="price" type="number" step="0.01" min="0" :label="__('catalog::vendor.own.fields.base_price')" :value="$money($product->price)" required dir="ltr" inputmode="decimal" />
                <x-ui::input name="sale_price" type="number" step="0.01" min="0" :label="__('catalog::vendor.own.fields.sale_price')" :value="$money($product->sale_price)" dir="ltr" inputmode="decimal" />
                <x-ui::input name="cost_price" type="number" step="0.01" min="0" :label="__('catalog::vendor.own.fields.cost_price')" :value="$money($product->cost_price)" dir="ltr" inputmode="decimal" />
                <x-ui::input name="stock" type="number" min="0" :label="__('catalog::vendor.edit.stock')" :value="$product->stock ?? 0" :helper="__('catalog::vendor.own.stock_hint')" dir="ltr" inputmode="numeric" data-product-stock />
            </div>
        </section>

        <section class="grid gap-4 rounded-[24px] bg-surface p-4 sm:p-6" data-variants data-next-index="{{ $variants->count() }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.own.variants') }}</h2>
                    <p class="text-xs text-ink-muted">{{ __('catalog::vendor.own.variants_hint') }}</p>
                </div>
                <x-ui::button variant="secondary" icon="plus" data-variant-add>{{ __('catalog::vendor.own.add_variant') }}</x-ui::button>
            </div>
            <p class="text-sm text-ink-muted" data-variants-empty @if ($variants->isNotEmpty()) hidden @endif>{{ __('catalog::vendor.own.no_variants') }}</p>
            <div class="grid gap-3" data-variant-list>
                @foreach ($variants as $index => $variant)
                    @include('catalog::vendor.own._variant-row', ['index' => $index, 'variant' => $variant, 'locales' => $locales, 'fallback' => $fallback])
                @endforeach
            </div>
            <template data-variant-template>
                @include('catalog::vendor.own._variant-row', ['index' => '__INDEX__', 'variant' => null, 'locales' => $locales, 'fallback' => $fallback])
            </template>
        </section>

        <section class="grid gap-4 rounded-[24px] bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-medium text-ink">{{ __('catalog::vendor.own.gallery') }}</h2>
                <p class="text-xs text-ink-muted">{{ __('catalog::vendor.own.gallery_hint') }}</p>
            </div>
            <x-ui::image-upload name="images" :images="$images" :max="12" accept="image/png,image/jpeg,image/webp,image/gif" />
        </section>
    </form>
    @if ($editing)
        @include('commerce::vendor.flash-sales._attach-modal')
        @include('ai::vendor.product-translation.modal', ['product' => $product])
        @include('ai::vendor.product-tools.modals', ['product' => $product])
    @endif
</x-vendor::layout>
