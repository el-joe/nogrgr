{{--
    Store banner create / edit (VEN-035a, Figma Vendor Panel 85:110108), loaded into
    #vendor-banner-modal: language tabs (title, subtitle, button text), main + mobile
    image tiles, link, schedule, countries (the store's selling countries), placement, active.
--}}
<form
    id="vendor-banner-form"
    method="POST"
    action="{{ $banner->exists ? vendor_route('store.banners.update', ['banner' => $banner->id]) : vendor_route('store.banners.store') }}"
    enctype="multipart/form-data"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('engagement::banners.save') }}"
>
    @csrf
    @if ($banner->exists)
        @method('PUT')
    @endif
    <x-ui::locale-tabs id="vendor-banner-i18n" :locales="$locales">
        @foreach ($locales as $code => $language)
            <x-ui::locale-pane parent="vendor-banner-i18n" :locale="$code">
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
        <x-ui::image-upload name="mobile_image[]" :label="__('engagement::banners.fields.mobile_image')" :images="$banner->exists && $banner->mobileImageUrl() ? [['id' => 'mobile', 'url' => $banner->mobileImageUrl(), 'alt' => (string) $banner->title]] : []" :max="1" accept="image/png,image/jpeg,image/webp,image/avif" :helper="__('engagement::banners.fields.mobile_image_help')" class="min-w-0 flex-1" />
    </div>
    <x-ui::input name="link_url" :label="__('engagement::banners.fields.link')" :value="$banner->link_url" maxlength="500" dir="ltr" placeholder="/c/bags" :helper="__('engagement::banners.fields.link_help')" />
    <x-ui::daterange name="dates" :label="__('engagement::banners.fields.dates')" :from="$banner->starts_at?->toDateString()" :to="$banner->ends_at?->toDateString()" :helper="__('engagement::banners.fields.dates_help')" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::select name="country_ids" multiple :label="__('engagement::banners.fields.countries')" :options="$countries" :selected="$countryIds" :placeholder="__('engagement::banners.countries_all')" :helper="__('engagement::banners.fields.countries_help')" />
        <x-ui::select name="placement" :label="__('engagement::banners.fields.placement')" :options="$placements" :selected="$banner->placement?->value" :search="false" required />
    </div>
    <x-ui::switch name="is_active" :label="__('engagement::banners.fields.active')" :checked="(bool) $banner->is_active" />
</form>
