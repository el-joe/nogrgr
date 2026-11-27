{{--
    Vendor › Your store › Themes (VEN-030, Figma Vendor Panel 85:104756), decision #119:
    one card per theme the store may use (active + allowed in at least one selling
    country) — preview, name, description, Default / "Active in: …" chips, Activate as
    store default, "Use in countries" (VEN-030a modal 85:110108), Preview (signed).
    The store's published custom Twig theme is listed first as its own entry (#121, use /
    stop with the custom theme switch). "Current themes" shows the theme of the store default and of each selling country
    (full table + per-country actions on Themes by country, VEN-031).
--}}
@php
    $defaultRow = $rows[0] ?? null;
    $active = $defaultRow?->theme;
    $countryRows = array_slice($rows, 1);
    $shownRows = array_slice($countryRows, 0, 5);
    $moreCount = count($countryRows) - count($shownRows);
    $hasSelection = collect($rows)->contains(fn ($row) => $row->ownTheme);
@endphp
<x-vendor::layout :title="__('content::vendor_themes.title')" page="vendor-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="__('content::vendor_themes.title')"
            :subtitle="__('content::vendor_themes.subtitle')"
            :breadcrumbs="[__('content::vendor_themes.crumb') => null, __('content::vendor_themes.title') => null]"
        >
            <x-slot:actions>
                @if ($canManage && $hasSelection && $platformDefault)
                    <x-ui::button variant="ghost" icon="refresh"
                        :href="vendor_route('store.themes.reset-platform')"
                        data-confirm="{{ __('content::vendor_themes.reset_platform_confirm', ['theme' => $platformDefault->name]) }}"
                        data-confirm-title="{{ __('content::vendor_themes.reset_platform_confirm_title') }}"
                        data-confirm-tone="warning"
                        data-confirm-method="DELETE">{{ __('content::vendor_themes.reset_platform') }}</x-ui::button>
                @endif
                @if ($countries !== [])
                    <x-ui::button variant="secondary" icon="globe" :href="vendor_route('store.home-variants')">{{ __('content::vendor_themes.by_country_title') }}</x-ui::button>
                @endif
                @if ($active)
                    <x-ui::button variant="secondary" icon="eye" :href="vendor_route('store.themes.preview', ['theme' => $active->id])" target="_blank" rel="noopener" :title="__('content::vendor_themes.preview_help')">{{ __('content::vendor_themes.preview_store') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        @if ($themes->isEmpty())
            <x-ui::card>
                <x-ui::empty-state icon="palette" :title="__('content::vendor_themes.empty.title')" :description="__('content::vendor_themes.empty.description')" />
            </x-ui::card>
        @else
            @if ($countries === [])
                <div class="flex flex-wrap items-center gap-3 rounded-card bg-info-bg px-4 py-3 text-sm text-ink" role="status" data-theme-no-countries>
                    <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />
                    <span class="min-w-0 flex-1">{{ __('content::vendor_themes.no_countries') }}</span>
                    @if ($countriesUrl)
                        <x-ui::button size="sm" variant="secondary" :href="$countriesUrl">{{ __('content::vendor_themes.countries_link') }}</x-ui::button>
                    @endif
                </div>
            @endif

            @if ($defaultRow)
                <x-ui::card id="vendor-theme-current" :title="__('content::vendor_themes.current_title')" :subtitle="__('content::vendor_themes.current_subtitle')">
                    <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" role="list">
                        @foreach (array_merge([$defaultRow], $shownRows) as $row)
                            <li class="vnd-theme-scope flex min-w-0 items-center gap-3 rounded-card border border-line bg-surface p-3" data-scope="{{ $row->scope() }}">
                                <span class="grid h-11 w-16 shrink-0 place-items-center overflow-hidden rounded-chip border border-line bg-media">
                                    @if ($row->theme?->preview_url)
                                        <img src="{{ $row->theme->preview_url }}" alt="" loading="lazy" decoding="async" class="size-full object-contain">
                                    @else
                                        <x-ui::icon name="palette" :size="18" class="text-ink-muted" />
                                    @endif
                                </span>
                                <span class="grid min-w-0 flex-1 gap-0.5">
                                    <span class="truncate text-xs text-ink-secondary">{{ $row->label }}</span>
                                    <span class="truncate text-sm font-semibold text-ink">{{ $row->theme?->name ?? __('content::vendor_themes.no_theme') }}</span>
                                </span>
                                @if ($row->countryId === null)
                                    <x-ui::badge :tone="$row->ownTheme ? 'brand' : 'neutral'">{{ $row->ownTheme ? __('content::vendor_themes.chosen_default') : __('content::vendor_themes.platform_default') }}</x-ui::badge>
                                @elseif ($row->ownTheme)
                                    <x-ui::badge tone="success">{{ __('content::vendor_themes.own_theme') }}</x-ui::badge>
                                @else
                                    <x-ui::badge>{{ __('content::vendor_themes.store_default') }}</x-ui::badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($moreCount > 0)
                        <div class="mt-3">
                            <x-ui::button size="sm" variant="ghost" icon-trailing="arrow-right" class="rtl:[&_svg]:-scale-x-100" :href="vendor_route('store.home-variants')">{{ __('content::vendor_themes.by_country_title') }} (+{{ $moreCount }})</x-ui::button>
                        </div>
                    @endif
                </x-ui::card>
            @endif

            @if ($customTheme ?? null)
                @include('storefront::vendor.themes._custom-theme-card')
            @endif

            <section aria-labelledby="vendor-theme-list-title" class="grid gap-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="vendor-theme-list-title" class="text-base font-semibold text-ink">{{ __('content::vendor_themes.all_themes') }}</h2>
                    <span class="text-sm text-ink-secondary">{{ trans_choice('content::vendor_themes.themes_count', $themes->count(), ['count' => $themes->count()]) }}</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-theme-grid>
                    @foreach ($themes as $theme)
                        @include('storefront::vendor.themes._theme-card', [
                            'theme' => $theme,
                            'use' => $usage[(int) $theme->id] ?? ['default' => false, 'countries' => []],
                            'allowedCountries' => $allowed[(int) $theme->id] ?? [],
                            'countries' => $countries,
                            'canManage' => $canManage,
                        ])
                    @endforeach
                </div>
            </section>
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
