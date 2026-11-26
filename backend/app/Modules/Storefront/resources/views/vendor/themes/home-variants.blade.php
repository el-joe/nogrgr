{{--
    Vendor › Your store › Home variants (VEN-031, Figma Vendor Panel 85:104756 "Home
    variant" section as its own page): store default + every selling country with the
    theme it renders, the home variant, own theme / follows default; Change variant =
    VEN-030b modal (85:110108), Use store default, Preview.
--}}
<x-vendor::layout :title="__('content::vendor_themes.variants_title')" page="vendor-themes">
    <div class="grid grid-cols-1 gap-6">
        <x-ui::page-header
            :title="__('content::vendor_themes.variants_title')"
            :subtitle="__('content::vendor_themes.variants_subtitle')"
            :breadcrumbs="[__('content::vendor_themes.crumb') => null, __('content::vendor_themes.variants_title') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="palette" :href="vendor_route('store.themes.index')">{{ __('content::vendor_themes.title') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <x-ui::card :padding="false">
            @if (! $hasCatalogue)
                <x-ui::empty-state icon="palette" :title="__('content::vendor_themes.empty.title')" :description="__('content::vendor_themes.empty.description')" />
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-start text-xs font-medium text-ink-secondary">
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('content::vendor_themes.columns.scope') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('content::vendor_themes.columns.theme') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('content::vendor_themes.columns.variant') }}</th>
                                <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('content::vendor_themes.columns.source') }}</th>
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
                                                @if ($theme->preview_url)
                                                    <img src="{{ $theme->preview_url }}" alt="" loading="lazy" class="h-10 w-16 shrink-0 rounded-chip border border-line object-contain bg-media">
                                                @endif
                                                <span class="text-ink">{{ $theme->family->label() }}</span>
                                            </span>
                                        @else
                                            <span class="text-ink-secondary">{{ __('content::vendor_themes.no_theme') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-ink">{{ $theme ? __('content::vendor_themes.home_variant', ['number' => $theme->variant]) : '—' }}</td>
                                    <td class="px-5 py-3">
                                        @if ($row->countryId === null)
                                            <x-ui::badge tone="brand">{{ __('content::vendor_themes.store_default') }}</x-ui::badge>
                                        @elseif ($row->ownTheme)
                                            <x-ui::badge tone="success">{{ __('content::vendor_themes.own_theme') }}</x-ui::badge>
                                        @else
                                            <x-ui::badge>{{ __('content::vendor_themes.follows_default') }}</x-ui::badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($canManage && $theme)
                                                <x-ui::button size="sm" variant="secondary" data-remote-modal="vendor-theme-variant-modal" :data-url="vendor_route('store.themes.variant', ['scope' => $row->scope(), 'return' => 'home-variants'])">{{ __('content::vendor_themes.change_variant') }}</x-ui::button>
                                            @endif
                                            @if ($canManage && $row->ownTheme)
                                                <x-ui::icon-button size="sm" icon="refresh" :label="__('content::vendor_themes.reset')"
                                                    :href="vendor_route('store.themes.reset', ['scope' => $row->countryId, 'return' => 'home-variants'])"
                                                    data-confirm="{{ __('content::vendor_themes.reset_confirm', ['country' => $row->label]) }}"
                                                    data-confirm-title="{{ __('content::vendor_themes.reset_confirm_title', ['country' => $row->label]) }}"
                                                    data-confirm-tone="warning"
                                                    data-confirm-method="DELETE" />
                                            @endif
                                            @if ($theme)
                                                <x-ui::icon-button size="sm" icon="eye" target="_blank" rel="noopener" :href="vendor_route('store.themes.preview', array_filter(['theme' => $theme->slug, 'country' => $row->countryId]))" :label="__('content::vendor_themes.preview_of', ['theme' => $row->label])" />
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
        <x-ui::modal id="vendor-theme-variant-modal" :title="__('content::vendor_themes.change_variant')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::vendor_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-theme-variant-form" data-remote-submit>{{ __('content::vendor_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
