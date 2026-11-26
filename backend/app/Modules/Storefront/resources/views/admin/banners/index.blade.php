{{--
    Admin › Content › Storefront › Banners (ADM-085, Figma Admin Panel 78:76059): country
    tabs with counts, a server-side card list per placement in sort order (drag handle,
    position, image, title, link · schedule), status, on / off switch, row actions; the
    create / edit modal is ADM-085a (78:82810). Sorting is saved on drop.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('storefront.banners.manage');
    $all = (int) ($counts[0] ?? 0);
    $tabItems = ['0' => ['label' => __('engagement::banners.all_countries'), 'href' => route('admin.storefront.banners.index', ['placement' => $placement->value]), 'count' => array_sum($counts)]];
    foreach ($countries as $id => $name) {
        $tabItems[(string) $id] = ['label' => $name, 'href' => route('admin.storefront.banners.index', ['country' => $id, 'placement' => $placement->value]), 'count' => (int) ($counts[$id] ?? 0) + $all];
    }
    $createUrl = route('admin.storefront.banners.create', array_filter(['country' => $country ?: null, 'placement' => $placement->value]));
@endphp
<x-layouts.panel surface="admin" :title="__('engagement::banners.title')" data-page="admin-banners">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('engagement::banners.title')"
            :subtitle="__('engagement::banners.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.banners') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="banner-modal" :data-url="$createUrl">{{ __('engagement::banners.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="banner-country-tabs" :active="(string) $country" :label="__('engagement::banners.fields.countries')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="banners"
            :url="route('admin.storefront.banners.data')"
            :columns="[
                ['data' => 'banner', 'title' => __('engagement::banners.columns.banner'), 'orderable' => false, 'searchable' => false, 'className' => 'w-full'],
                ['data' => 'status', 'title' => __('engagement::banners.columns.status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :page-length="100"
            :per-page="[100]"
            :empty-title="__('engagement::banners.empty.title')"
            :empty-description="__('engagement::banners.empty.description')"
            :data-banner-sort="$canManage ? route('admin.storefront.banners.sort') : null"
        >
            <x-slot:filters>
                <div class="grid gap-3">
                    <h2 class="text-base font-semibold text-ink">{{ __('engagement::banners.list_title', ['placement' => $placement->label()]) }}</h2>
                    <x-ui::filters-bar target="banners" :search-placeholder="__('engagement::banners.search')">
                        <input type="hidden" name="country_id" value="{{ $country }}" data-filter>
                        <x-ui::select name="placement" :label="__('engagement::banners.fields.placement')" label-hidden :options="$placements" :selected="$placement->value" :search="false" data-filter data-filter-fixed :data-filter-label="__('engagement::banners.fields.placement')" class="w-56" />
                    </x-ui::filters-bar>
                </div>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="banner-modal" :data-url="$createUrl">{{ __('engagement::banners.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @if ($canManage)
        <x-ui::modal id="banner-modal" :title="__('engagement::banners.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('engagement::banners.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="banner-form" icon="check" data-remote-submit>{{ __('engagement::banners.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
