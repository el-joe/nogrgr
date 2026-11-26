{{--
    Vendor › Your store › Banners (VEN-035, Figma Vendor Panel 85:108271): country tabs
    (selling countries, with counts), "Show platform default banners" switch, plan quota,
    a server-side card list per placement in storefront order — store banners (drag to
    sort, status, on / off, edit / delete) merged with the platform's banners ("Admin",
    show / hide on this store). Create / edit = VEN-035a modal (85:110108).
--}}
@php
    $app = config('app.name');
    $tabItems = [];
    foreach ($countries as $id => $name) {
        $tabItems[(string) $id] = ['label' => $name, 'href' => vendor_route('store.banners.index', ['country' => $id, 'placement' => $placement->value]), 'count' => number_format((int) ($counts[$id] ?? 0))];
    }
    $createUrl = vendor_route('store.banners.create', array_filter(['country' => $country, 'placement' => $placement->value]));
    $blocked = $quota['reached'];
@endphp
<x-vendor::layout :title="__('engagement::vendor_banners.title')" page="vendor-banners">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('engagement::vendor_banners.title')"
            :subtitle="__('engagement::vendor_banners.subtitle')"
            :breadcrumbs="[__('engagement::vendor_banners.crumb') => null, __('engagement::vendor_banners.title') => null]"
        >
            @if ($canManage && $country !== null)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="vendor-banner-modal" :data-url="$createUrl" :disabled="$blocked" data-live="banner-add">{{ __('engagement::vendor_banners.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            @if ($tabItems !== [])
                <x-slot:tabs>
                    <x-ui::tabs id="vendor-banner-country-tabs" :active="(string) $country" :label="__('engagement::vendor_banners.countries')" :items="$tabItems" />
                </x-slot:tabs>
            @endif
        </x-ui::page-header>

        @if ($country === null)
            <x-ui::card>
                <x-ui::empty-state :title="__('engagement::vendor_banners.empty.title')" :description="__('engagement::vendor_banners.no_countries')" />
            </x-ui::card>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($hasCentral)
                    <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-ink">
                        <input type="checkbox" role="switch" class="ui-switch" name="show_defaults" @checked($defaultsVisible) @disabled(! $canManage) @if ($canManage) data-banner-defaults="{{ vendor_route('store.banners.defaults') }}" @endif aria-describedby="banner-defaults-help">
                        <span class="grid gap-0.5">
                            <span>{{ __('engagement::vendor_banners.show_defaults', ['app' => $app]) }}</span>
                            <span id="banner-defaults-help" class="text-xs text-ink-secondary">{{ __('engagement::vendor_banners.show_defaults_help') }}</span>
                        </span>
                    </label>
                @else
                    <span></span>
                @endif
                <p class="text-xs text-ink-secondary" data-banner-quota data-live="banner-quota">
                    {{ $quota['max'] === null
                        ? trans_choice('engagement::vendor_banners.quota_unlimited', $quota['used'], ['used' => number_format($quota['used'])])
                        : __('engagement::vendor_banners.quota', ['used' => number_format($quota['used']), 'max' => number_format($quota['max'])]) }}
                </p>
            </div>

            {{-- Live region (QA-P8-005): appears / disappears after a create or delete without a reload. --}}
            <div class="contents" data-live="banner-limit">
            @if ($canManage && $blocked)
                <div class="flex flex-wrap items-center gap-3 rounded-card bg-warning-bg px-4 py-3 text-sm text-ink" role="status">
                    <x-ui::icon name="info" :size="16" class="shrink-0 text-warning" />
                    <span class="flex-1">{{ __('engagement::vendor_banners.limit_reached', ['max' => number_format((int) $quota['max'])]) }}</span>
                    @if (\Illuminate\Support\Facades\Route::has('vendor.finance.subscription') && auth('vendor')->user()?->can('finance.subscription.view'))
                        <x-ui::button size="sm" variant="secondary" :href="vendor_route('finance.subscription')">{{ __('engagement::vendor_banners.upgrade') }}</x-ui::button>
                    @endif
                </div>
            @endif
            </div>

            <x-ui::datatable
                id="vendor-banners"
                :url="vendor_route('store.banners.data')"
                :columns="[
                    ['data' => 'banner', 'title' => __('engagement::banners.columns.banner'), 'orderable' => false, 'searchable' => false, 'className' => 'w-full'],
                    ['data' => 'status', 'title' => __('engagement::banners.columns.status'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :page-length="100"
                :per-page="[100]"
                :empty-title="__('engagement::vendor_banners.empty.title')"
                :empty-description="__('engagement::vendor_banners.empty.description')"
                :data-banner-sort="$canManage ? vendor_route('store.banners.sort') : null"
            >
                <x-slot:filters>
                    <div class="grid gap-3">
                        <h2 class="text-base font-semibold text-ink">{{ __('engagement::vendor_banners.list_title', ['placement' => $placement->label()]) }}</h2>
                        <x-ui::filters-bar target="vendor-banners" :search-placeholder="__('engagement::banners.search')">
                            <input type="hidden" name="country_id" value="{{ $country }}" data-filter>
                            <x-ui::select name="placement" :label="__('engagement::banners.fields.placement')" label-hidden :options="$placements" :selected="$placement->value" :search="false" data-filter data-filter-fixed :data-filter-label="__('engagement::banners.fields.placement')" class="w-56" />
                        </x-ui::filters-bar>
                    </div>
                </x-slot:filters>
                @if ($canManage && ! $blocked)
                    <x-slot:empty>
                        <x-ui::button icon="plus" data-remote-modal="vendor-banner-modal" :data-url="$createUrl">{{ __('engagement::vendor_banners.add') }}</x-ui::button>
                    </x-slot:empty>
                @endif
            </x-ui::datatable>
        @endif
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-banner-modal" :title="__('engagement::vendor_banners.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('engagement::banners.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-banner-form" data-remote-submit>{{ __('engagement::banners.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
