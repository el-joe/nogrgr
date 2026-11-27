{{--
    Vendor › Your store › Themes by country (VEN-031, route vendor.store.home-variants kept
    for old links), decision #119: store default + every selling country with the theme
    it renders and its source (own theme / follows the store default / platform default);
    Change = VEN-030b theme modal (85:110108), Use store default, Preview.
--}}
<x-vendor::layout :title="__('content::vendor_themes.by_country_title')" page="vendor-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="__('content::vendor_themes.by_country_title')"
            :subtitle="__('content::vendor_themes.by_country_subtitle')"
            :breadcrumbs="[__('content::vendor_themes.crumb') => null, __('content::vendor_themes.title') => vendor_route('store.themes.index'), __('content::vendor_themes.by_country_title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="palette" :href="vendor_route('store.themes.index')">{{ __('content::vendor_themes.all_themes') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        @if ($hasCatalogue && $countries === [])
            <div class="flex items-center gap-3 rounded-card bg-info-bg px-4 py-3 text-sm text-ink" role="status" data-theme-no-countries>
                <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />
                <span>{{ __('content::vendor_themes.no_countries') }}</span>
            </div>
        @endif

        <x-ui::card :padding="false">
            @if (! $hasCatalogue)
                <x-ui::empty-state icon="palette" :title="__('content::vendor_themes.empty.title')" :description="__('content::vendor_themes.empty.description')" />
            @else
                <div class="relative overflow-x-auto">
                    <table data-lead class="vnd-table w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="text-start text-sm text-ink-secondary">
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::vendor_themes.columns.scope') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::vendor_themes.columns.theme') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-normal">{{ __('content::vendor_themes.columns.source') }}</th>
                                <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('ui.table.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                @php $theme = $row->theme; @endphp
                                <tr data-scope="{{ $row->scope() }}">
                                    <th scope="row" class="px-5 py-3 text-start font-medium text-ink">{{ $row->label }}</th>
                                    <td class="px-5 py-3">
                                        @if ($theme)
                                            <span class="flex items-center gap-3">
                                                <span class="grid h-10 w-16 shrink-0 place-items-center overflow-hidden rounded-chip border border-line bg-media">
                                                    @if ($theme->preview_url)
                                                        <img src="{{ $theme->preview_url }}" alt="" loading="lazy" decoding="async" class="size-full object-contain">
                                                    @else
                                                        <x-ui::icon name="palette" :size="16" class="text-ink-muted" />
                                                    @endif
                                                </span>
                                                <span class="font-medium text-ink">{{ $theme->name }}</span>
                                            </span>
                                        @else
                                            <span class="text-ink-secondary">{{ __('content::vendor_themes.no_theme') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($row->countryId === null)
                                            <x-ui::badge :tone="$row->ownTheme ? 'brand' : 'neutral'">{{ $row->ownTheme ? __('content::vendor_themes.chosen_default') : __('content::vendor_themes.follows_platform') }}</x-ui::badge>
                                        @elseif ($row->ownTheme)
                                            <x-ui::badge tone="success">{{ __('content::vendor_themes.own_theme') }}</x-ui::badge>
                                        @else
                                            <x-ui::badge>{{ __('content::vendor_themes.follows_default') }}</x-ui::badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($canManage)
                                                <x-ui::button size="sm" variant="secondary" data-remote-modal="vendor-theme-scope-modal" :data-url="vendor_route('store.themes.scope', ['scope' => $row->scope(), 'return' => 'by-country'])">{{ __('content::vendor_themes.change') }}</x-ui::button>
                                            @endif
                                            @if ($canManage && $row->countryId !== null && $row->ownTheme)
                                                <x-ui::icon-button size="sm" icon="refresh" :label="__('content::vendor_themes.reset')"
                                                    :href="vendor_route('store.themes.reset', ['scope' => $row->countryId, 'return' => 'by-country'])"
                                                    data-confirm="{{ __('content::vendor_themes.reset_confirm', ['country' => $row->label]) }}"
                                                    data-confirm-title="{{ __('content::vendor_themes.reset_confirm_title', ['country' => $row->label]) }}"
                                                    data-confirm-tone="warning"
                                                    data-confirm-method="DELETE" />
                                            @endif
                                            @if ($theme)
                                                <x-ui::icon-button size="sm" icon="eye" target="_blank" rel="noopener" :href="vendor_route('store.themes.preview', array_filter(['theme' => $theme->id, 'country' => $row->countryId]))" :label="__('content::vendor_themes.preview_of', ['theme' => $row->label])" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui::card>
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-theme-scope-modal" :title="__('content::vendor_themes.change_theme')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::vendor_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-theme-scope-form" data-remote-submit>{{ __('content::vendor_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
