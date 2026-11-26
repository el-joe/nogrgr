{{--
    Vendor › Your store › Appearance (VEN-034, Figma Vendor Panel 85:106903 / 85:107559): tabs
    Logo, Colours & fonts, Promo banner, Header links, Footer, Social links — each tab has
    its own Save. Everything applies to the store's active theme (Ecommet / Elora / Souqify).
    The AI logo generator of the Logo tab is VEN-034a (P10); the logo upload is here.
--}}
@php
    $tabItems = collect($tabs)->mapWithKeys(fn (string $tab) => [$tab => __('content::store_appearance.tabs.'.$tab)])->all();
    $tenant = tenant();
    $storeUrl = $tenant instanceof \App\Modules\Platform\Models\Tenant ? \App\Modules\Platform\Services\StoreSettingsService::storeUrl($tenant) : null;
@endphp
<x-vendor::layout :title="__('content::store_appearance.title')" page="vendor-appearance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('content::store_appearance.title')" :subtitle="__('content::store_appearance.subtitle')" :breadcrumbs="[__('content::store_appearance.crumb') => null, __('content::store_appearance.title') => null]">
            @if ($storeUrl)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="eye" :href="$storeUrl" target="_blank" rel="noopener">{{ __('content::store_appearance.preview_store') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="appearance-tabs" :items="$tabItems" :label="__('content::store_appearance.title')" />
            </x-slot:tabs>
        </x-ui::page-header>

        @unless ($canManage)
            <div class="flex items-center gap-3 rounded-card bg-info-bg px-4 py-3 text-sm text-ink" role="status">
                <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />{{ __('content::store_appearance.read_only') }}
            </div>
        @endunless

        <x-ui::tab-panel tabs="appearance-tabs" key="logo" :active="request()->query('tab', 'logo') === 'logo' || ! in_array(request()->query('tab'), $tabs, true)">
            @include('storefront::vendor.appearance._logo')
        </x-ui::tab-panel>
        <x-ui::tab-panel tabs="appearance-tabs" key="colors" :active="request()->query('tab') === 'colors'">
            @include('storefront::vendor.appearance._colors')
        </x-ui::tab-panel>
        <x-ui::tab-panel tabs="appearance-tabs" key="promo" :active="request()->query('tab') === 'promo'">
            @include('storefront::vendor.appearance._promo')
        </x-ui::tab-panel>
        <x-ui::tab-panel tabs="appearance-tabs" key="header" :active="request()->query('tab') === 'header'">
            @include('storefront::vendor.appearance._links', ['placement' => 'header', 'links' => $headerLinks])
        </x-ui::tab-panel>
        <x-ui::tab-panel tabs="appearance-tabs" key="footer" :active="request()->query('tab') === 'footer'">
            @include('storefront::vendor.appearance._footer')
        </x-ui::tab-panel>
        <x-ui::tab-panel tabs="appearance-tabs" key="social" :active="request()->query('tab') === 'social'">
            @include('storefront::vendor.appearance._social')
        </x-ui::tab-panel>
    </div>

    @if ($canManage)
        <x-ui::modal id="appearance-modal" :title="__('content::store_appearance.links.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::store_appearance.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="appearance-link-form" data-remote-submit>{{ __('content::store_appearance.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
