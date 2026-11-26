{{--
    Vendor › Your store › Themes (VEN-030, Figma Vendor Panel 85:104756): tabs = store
    default + selling countries; family cards (active one ringed, Activate / Use in
    countries… = VEN-030a modal 85:110108 / Preview); "Home variant — <theme> (<scope>)"
    cards of the active family (Select / Preview). A country tab shows whether it has its
    own theme or follows the store default ("Use store default" resets it).
--}}
@php
    use App\Modules\Storefront\Services\Themes\ThemePageService;

    $isDefault = $scope === ThemePageService::DEFAULT_SCOPE;
    $countryId = $row->countryId;
    $tabItems = [ThemePageService::DEFAULT_SCOPE => ['label' => __('content::vendor_themes.store_default'), 'href' => vendor_route('store.themes.index')]];
    foreach ($countries as $id => $name) {
        $tabItems[(string) $id] = ['label' => $name, 'href' => vendor_route('store.themes.index', ['country' => $id])];
    }
    $active = $row->theme;
    $previewParams = fn ($theme) => array_filter(['theme' => $theme->slug, 'country' => $countryId]);
    $target = $isDefault ? ['default' => 1] : ['country_ids' => [$countryId]];
@endphp
<x-vendor::layout :title="__('content::vendor_themes.title')" page="vendor-themes">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('content::vendor_themes.title')"
            :subtitle="__('content::vendor_themes.subtitle')"
            :breadcrumbs="[__('content::vendor_themes.crumb') => null, __('content::vendor_themes.title') => null]"
        >
            @if ($active)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="eye" :href="vendor_route('store.themes.preview', $previewParams($active))" target="_blank" rel="noopener" :title="__('content::vendor_themes.preview_help')">{{ __('content::vendor_themes.preview_store') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="vendor-theme-scope-tabs" :active="$scope" :label="__('content::vendor_themes.scopes')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($families === [])
            <x-ui::card>
                <x-ui::empty-state icon="palette" :title="__('content::vendor_themes.empty.title')" :description="__('content::vendor_themes.empty.description')" />
            </x-ui::card>
        @else
            @if ($countries === [])
                <div class="flex items-center gap-3 rounded-card bg-info-bg px-4 py-3 text-sm text-ink" role="status">
                    <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />
                    <span>{{ __('content::vendor_themes.no_countries') }}</span>
                </div>
            @elseif (! $isDefault)
                <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-sm text-ink" role="status">
                    <x-ui::icon name="globe" :size="16" class="shrink-0 text-ink-secondary" />
                    <span class="min-w-0 flex-1">{{ $row->ownTheme ? __('content::vendor_themes.own_theme_notice', ['country' => $row->label]) : __('content::vendor_themes.follows_default_notice', ['country' => $row->label]) }}</span>
                    @if ($row->ownTheme && $canManage)
                        <x-ui::button size="sm" variant="secondary" icon="refresh"
                            :href="vendor_route('store.themes.reset', ['scope' => $countryId])"
                            data-confirm="{{ __('content::vendor_themes.reset_confirm', ['country' => $row->label]) }}"
                            data-confirm-title="{{ __('content::vendor_themes.reset_confirm_title', ['country' => $row->label]) }}"
                            data-confirm-tone="warning"
                            data-confirm-method="DELETE">{{ __('content::vendor_themes.reset') }}</x-ui::button>
                    @endif
                </div>
            @endif

            <section aria-labelledby="vendor-theme-families-title" class="grid gap-4">
                <h2 id="vendor-theme-families-title" class="sr-only">{{ __('content::vendor_themes.title') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($families as $family => $themes)
                        @php
                            $isActive = $active !== null && $active->family->value === $family;
                            $cover = $isActive ? $active : $themes->first();
                        @endphp
                        @include('storefront::vendor.themes._theme-card', [
                            'theme' => $cover,
                            'name' => $cover->family->label(),
                            'detail' => $isActive
                                ? __('content::vendor_themes.active_in', ['scope' => $row->label, 'variant' => $active->variant])
                                : trans_choice('content::vendor_themes.variants_count', $themes->count(), ['count' => $themes->count()]),
                            'selected' => $isActive,
                            'badge' => __('content::vendor_themes.active'),
                            'previewUrl' => vendor_route('store.themes.preview', $previewParams($cover)),
                            'form' => $canManage && ! $isActive ? ['action' => vendor_route('store.themes.activate'), 'method' => 'POST', 'fields' => ['theme' => $cover->slug, 'scope' => $scope] + $target, 'label' => __('content::vendor_themes.activate')] : null,
                            'modalUrl' => $canManage && $countries !== [] ? vendor_route('store.themes.countries', ['theme' => $cover->slug]) : null,
                        ])
                    @endforeach
                </div>
            </section>

            @if ($active)
                @php $variants = $families[$active->family->value] ?? collect(); @endphp
                <x-ui::card id="vendor-theme-variants" :title="__('content::vendor_themes.variant_section', ['theme' => $active->family->label(), 'scope' => $row->label])">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($variants as $variant)
                            @php $isSelected = $variant->slug === $active->slug; @endphp
                            @include('storefront::vendor.themes._theme-card', [
                                'theme' => $variant,
                                'name' => __('content::vendor_themes.home_variant', ['number' => $variant->variant]),
                                'detail' => (string) ($variant->description ?: $variant->name),
                                'selected' => $isSelected,
                                'badge' => __('content::vendor_themes.selected'),
                                'previewUrl' => vendor_route('store.themes.preview', $previewParams($variant)),
                                'form' => $canManage && ! $isSelected ? ['action' => vendor_route('store.themes.variant.update', ['scope' => $scope]), 'method' => 'PUT', 'fields' => ['variant' => $variant->variant], 'label' => __('content::vendor_themes.activate')] : null,
                                'modalUrl' => null,
                                'compact' => true,
                            ])
                        @endforeach
                    </div>
                </x-ui::card>
            @endif
        @endif
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-theme-modal" :title="__('content::vendor_themes.use_in_countries')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::vendor_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-theme-countries-form" data-remote-submit>{{ __('content::vendor_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
