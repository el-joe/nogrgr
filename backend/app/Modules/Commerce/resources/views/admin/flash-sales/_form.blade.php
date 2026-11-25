{{--
    Flash sale create / edit / view (ADM-026a, Figma Admin Panel 70:41386), loaded into
    #flash-sale-modal (or #flash-sale-view-modal read-only): title per language, discount
    %, countries, dates, central product picker (remote search), stores, banner upload,
    active.
--}}
@php
    $scope = $sale->store_scope?->value ?? 'all';
    $stores = $sale->relationLoaded('tenants') ? $sale->tenants->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all() : [];
    $productOptions = collect($products)->mapWithKeys(fn ($option) => [$option['id'] => $option['text']])->all();
    $banner = $sale->bannerUrl();
@endphp
<form
    id="{{ $editable ? 'flash-sale-form' : 'flash-sale-view' }}"
    method="POST"
    action="{{ $sale->exists ? route('admin.promotions.flash-sales.update', $sale) : route('admin.promotions.flash-sales.store') }}"
    enctype="multipart/form-data"
    @if ($editable) data-ajax @endif
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('commerce::promotions.save') }}"
    x-data="{{ json_encode(['scope' => $scope]) }}"
    x-on:change="if ($event.target.name === 'store_scope') scope = $event.target.value"
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
            <x-ui::input name="discount_percentage" :label="__('commerce::flash_sales.fields.discount')" :value="$sale->discount_percentage !== null ? rtrim(rtrim(number_format((float) $sale->discount_percentage, 2, '.', ''), '0'), '.') : null" inputmode="decimal" dir="ltr" placeholder="25" :helper="__('commerce::flash_sales.fields.discount_help')" required />
            <x-ui::select name="country_ids" multiple :label="__('commerce::flash_sales.fields.countries')" :options="$countries" :selected="$sale->exists ? $sale->countries->pluck('id')->all() : []" :placeholder="__('commerce::promotions.all_countries')" :helper="__('commerce::promotions.countries_help')" />
        </div>
        <x-ui::daterange name="dates" :label="__('commerce::flash_sales.fields.dates')" :from="$sale->starts_at?->toDateString()" :to="$sale->ends_at?->toDateString()" required />
        <x-ui::select name="product_ids" multiple :label="__('commerce::flash_sales.fields.products')" :options="$productOptions" :selected="array_keys($productOptions)" :ajax-url="$editable ? route('admin.promotions.flash-sales.products') : null" :placeholder="__('commerce::flash_sales.fields.search_products')" :helper="__('commerce::flash_sales.fields.products_help')" required />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::select name="store_scope" :label="__('commerce::flash_sales.fields.stores')" :options="$scopes" :selected="$scope" :search="false" :helper="__('commerce::promotions.stores_help')" required />
            <div x-show="scope === 'selected'" @if ($scope !== 'selected') x-cloak @endif>
                <x-ui::select name="tenant_ids" multiple :label="__('commerce::flash_sales.fields.selected_stores')" :options="$stores" :selected="array_keys($stores)" :ajax-url="$editable ? route('admin.promotions.flash-sales.stores') : null" :placeholder="__('commerce::flash_sales.fields.search_stores')" />
            </div>
        </div>
        <x-ui::image-upload name="banner[]" :label="__('commerce::flash_sales.fields.banner')" :images="$banner ? [['id' => 'banner', 'url' => $banner, 'alt' => (string) $sale->title]] : []" :max="1" accept="image/png,image/jpeg,image/webp" :helper="__('commerce::flash_sales.fields.banner_help')" />
        <x-ui::switch name="is_active" :label="__('commerce::flash_sales.fields.active')" :checked="(bool) $sale->is_active" />
    </fieldset>
</form>
