{{--
    Admin › Vendors › Shoppers (ADM-043, Figma 73:52854): read-only buyers of every store
    from central order facts (personal data masked). KPI cards, store / country / status /
    new-only filters, server-side table and CSV export of the filtered rows.
--}}
@php
    $canExport = (bool) auth('admin')->user()?->can('vendors.shoppers.export');
@endphp
<x-layouts.panel surface="admin" :title="__('reporting::shoppers.title')" data-page="admin-reports">
    <div class="grid gap-6 *:min-w-0">
        <x-ui::page-header
            :title="__('reporting::shoppers.title')"
            :subtitle="__('reporting::shoppers.subtitle')"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('reporting::shoppers.title') => null]"
        >
            @if ($canExport)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="download" :href="route('admin.vendors.shoppers.export')" data-export-for="shoppers">{{ __('reporting::shoppers.export') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('reporting::shoppers.title') }}">
            @foreach ($kpis as $kpi)
                <x-ui::kpi-card
                    :label="__('reporting::shoppers.kpi.'.$kpi['key'])"
                    :value="$kpi['value']"
                    :icon="$kpi['icon']"
                    :comparison="__('reporting::shoppers.kpi_hint.'.$kpi['key'])"
                    :count-up="$kpi['raw']"
                    :count-format="$kpi['format']"
                    data-reveal="fade-up"
                    :data-reveal-delay="$loop->index * 60"
                />
            @endforeach
        </section>

        <x-ui::datatable
            id="shoppers"
            :url="route('admin.vendors.shoppers.data')"
            :columns="[
                ['data' => 'shopper', 'name' => 'shopper', 'title' => __('reporting::shoppers.columns.shopper')],
                ['data' => 'store', 'name' => 'store', 'title' => __('reporting::shoppers.columns.store')],
                ['data' => 'country', 'name' => 'country', 'title' => __('reporting::shoppers.columns.country')],
                ['data' => 'orders_cell', 'name' => 'orders', 'title' => __('reporting::shoppers.columns.orders'), 'align' => 'end'],
                ['data' => 'spent_cell', 'name' => 'spent', 'title' => __('reporting::shoppers.columns.spent'), 'align' => 'end'],
                ['data' => 'status_cell', 'name' => 'status', 'title' => __('reporting::shoppers.columns.status')],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('reporting::shoppers.empty_title')"
            :empty-description="__('reporting::shoppers.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="shoppers" :search-placeholder="__('reporting::shoppers.search')">
                    <x-ui::select name="tenant_id" :label="__('reporting::shoppers.filters.store')" label-hidden :options="$selectedStore" :selected="$filters->tenantId" :placeholder="__('reporting::shoppers.filters.store')" :ajax-url="route('admin.reports.stores')" allow-clear data-filter :data-filter-label="__('reporting::shoppers.filters.store')" class="w-48" />
                    <x-ui::select name="country_id" :label="__('reporting::shoppers.filters.country')" label-hidden :options="$countries" :selected="$filters->countryId" :placeholder="__('reporting::shoppers.filters.country')" allow-clear data-filter :data-filter-label="__('reporting::shoppers.filters.country')" class="w-44" />
                    <x-ui::select name="status" :label="__('reporting::shoppers.filters.status')" label-hidden :options="$statuses" :placeholder="__('reporting::shoppers.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('reporting::shoppers.filters.status')" class="w-36" />
                    <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="new_only" value="1" class="ui-check" data-filter data-filter-label="{{ __('reporting::shoppers.filters.new_only') }}">
                        {{ __('reporting::shoppers.filters.new_only') }}
                    </label>
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
