{{--
    Store flash sale create / edit / view (VEN-041a, Figma Tenant Panel 87:111915), loaded
    into #vendor-flash-sale-modal (or #vendor-flash-sale-view-modal read-only): title per
    language, discount %, dates, countries (the store's selling countries), store product
    picker (remote search over imported + own products), banner upload, active.
--}}
@php
    $banner = $sale->bannerUrl();
    $countryIds = $sale->exists ? $sale->countryRows->pluck('country_id')->map(fn ($id) => (int) $id)->all() : [];
@endphp
<form
    id="{{ $editable ? 'vendor-flash-sale-form' : 'vendor-flash-sale-view' }}"
    method="POST"
    action="{{ $sale->exists ? vendor_route('marketing.flash-sales.update', ['flashSale' => $sale->id]) : vendor_route('marketing.flash-sales.store') }}"
    enctype="multipart/form-data"
    @if ($editable) data-ajax @endif
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('commerce::promotions.save') }}"
>
    @csrf
    @if ($sale->exists)
        @method('PUT')
    @endif
    <fieldset @disabled(! $editable) class="grid gap-5">
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($locales as $code => $language)
                <x-ui::input :name="'title['.$code.']'" :label="__('commerce::flash_sales.fields.title_locale', ['locale' => strtoupper($code)])" :value="$sale->exists ? $sale->getTranslation('title', $code, false) : null" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="120" :dir="$language['dir'] ?? 'ltr'" />
            @endforeach
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::input name="discount_percentage" :label="__('commerce::flash_sales.fields.discount')" :value="$sale->discount_percentage !== null ? rtrim(rtrim(number_format((float) $sale->discount_percentage, 2, '.', ''), '0'), '.') : null" inputmode="decimal" dir="ltr" placeholder="20%" :helper="__('commerce::flash_sales.fields.discount_help')" required />
            <x-ui::select name="country_ids" multiple :label="__('commerce::flash_sales.fields.countries')" :options="$countries" :selected="$countryIds" :placeholder="__('commerce::promotions.all_countries')" :helper="__('commerce::promotions.countries_help')" />
        </div>
        <x-ui::daterange name="dates" :label="__('commerce::flash_sales.fields.dates')" :from="$sale->starts_at?->toDateString()" :to="$sale->ends_at?->toDateString()" required />
        <x-ui::select name="products" multiple :label="__('commerce::flash_sales.fields.products')" :options="$products" :selected="array_keys($products)" :ajax-url="$editable ? vendor_route('marketing.flash-sales.products.search') : null" :placeholder="__('commerce::vendor_promotions.flash_sales.search_products')" :helper="__('commerce::vendor_promotions.flash_sales.products_help')" required />
        <x-ui::image-upload name="banner[]" :label="__('commerce::flash_sales.fields.banner')" :images="$banner ? [['id' => 'banner', 'url' => $banner, 'alt' => (string) $sale->title]] : []" :max="1" accept="image/png,image/jpeg,image/webp" :helper="__('commerce::flash_sales.fields.banner_help')" />
        <x-ui::switch name="is_active" :label="__('commerce::flash_sales.fields.active')" :checked="(bool) $sale->is_active" />
    </fieldset>
</form>
