{{--
    Banner create / edit (ADM-085a, Figma Admin Panel 78:82810), loaded into #banner-modal:
    language tabs (title, subtitle, button text), main + mobile image tiles, link URL,
    date range, countries, placement, stores, active.
--}}
@php
    $scope = $banner->store_scope?->value ?? 'all';
    $stores = $banner->relationLoaded('tenants') ? $banner->tenants->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all() : [];
@endphp
<form
    id="banner-form"
    method="POST"
    action="{{ $banner->exists ? route('admin.storefront.banners.update', $banner) : route('admin.storefront.banners.store') }}"
    enctype="multipart/form-data"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('engagement::banners.save') }}"
    x-data="{{ json_encode(['scope' => $scope]) }}"
    x-on:change="if ($event.target.name === 'store_scope') scope = $event.target.value"
>
    @csrf
    @if ($banner->exists)
        @method('PUT')
    @endif
    <x-ui::locale-tabs id="banner-i18n" :locales="$locales">
        @foreach ($locales as $code => $language)
            <x-ui::locale-pane parent="banner-i18n" :locale="$code">
                <div class="grid gap-5">
                    <x-ui::input :name="'title['.$code.']'" :label="__('engagement::banners.fields.title')" :value="$banner->exists ? $banner->getTranslation('title', $code, false) : null" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="120" :dir="$language['dir'] ?? 'ltr'" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui::input :name="'subtitle['.$code.']'" :label="__('engagement::banners.fields.subtitle')" :value="$banner->exists ? $banner->getTranslation('subtitle', $code, false) : null" maxlength="160" :dir="$language['dir'] ?? 'ltr'" />
                        <x-ui::input :name="'cta_label['.$code.']'" :label="__('engagement::banners.fields.cta_label')" :value="$banner->exists ? $banner->getTranslation('cta_label', $code, false) : null" maxlength="40" :dir="$language['dir'] ?? 'ltr'" />
                    </div>
                </div>
            </x-ui::locale-pane>
        @endforeach
    </x-ui::locale-tabs>
    <div class="flex flex-wrap gap-5">
        <x-ui::image-upload name="image[]" :label="__('engagement::banners.fields.image')" :images="$banner->exists ? [['id' => 'image', 'url' => $banner->imageUrl(), 'alt' => (string) $banner->title]] : []" :max="1" accept="image/png,image/jpeg,image/webp,image/avif" :helper="__('engagement::banners.fields.image_help')" :required="! $banner->exists" class="min-w-0 flex-1" />
        <x-ui::image-upload name="mobile_image[]" :label="__('engagement::banners.fields.mobile_image')" :images="$banner->mobileImageUrl() ? [['id' => 'mobile', 'url' => $banner->mobileImageUrl(), 'alt' => (string) $banner->title]] : []" :max="1" accept="image/png,image/jpeg,image/webp,image/avif" :helper="__('engagement::banners.fields.mobile_image_help')" class="min-w-0 flex-1" />
    </div>
    <x-ui::input name="link_url" :label="__('engagement::banners.fields.link')" :value="$banner->link_url" maxlength="500" dir="ltr" placeholder="/flash-sales/white-friday" :helper="__('engagement::banners.fields.link_help')" />
    <x-ui::daterange name="dates" :label="__('engagement::banners.fields.dates')" :from="$banner->starts_at?->toDateString()" :to="$banner->ends_at?->toDateString()" :helper="__('engagement::banners.fields.dates_help')" />
    <x-ui::select name="country_ids" multiple :label="__('engagement::banners.fields.countries')" :options="$countries" :selected="$countryIds" :placeholder="__('engagement::banners.countries_all')" :helper="__('engagement::banners.fields.countries_help')" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="placement" :label="__('engagement::banners.fields.placement')" :options="$placements" :selected="$banner->placement?->value" :search="false" required />
        <x-ui::select name="store_scope" :label="__('engagement::banners.fields.stores')" :options="$scopes" :selected="$scope" :search="false" :helper="__('engagement::banners.fields.stores_help')" required />
    </div>
    <div x-show="scope === 'selected'" @if ($scope !== 'selected') x-cloak @endif>
        <x-ui::select name="tenant_ids" multiple :label="__('engagement::banners.fields.selected_stores')" :options="$stores" :selected="array_keys($stores)" :ajax-url="route('admin.storefront.banners.stores')" :placeholder="__('engagement::banners.fields.search_stores')" />
    </div>
    <x-ui::switch name="is_active" :label="__('engagement::banners.fields.active')" :checked="(bool) $banner->is_active" />
</form>
