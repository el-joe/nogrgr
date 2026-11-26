{{--
    Admin › Reports (ADM-070 + tabs 070a…070k; Figma 75:68061 Sales, 75:69019 Orders /
    Profit / Refunds / Shipping / Promotions, 75:69998 Subscriptions / Affiliates / Vendors /
    Products / Customers). Header: period, store and country filters (page reload, URL
    synced) + CSV export; underline tabs; KPI cards, highlight tiles, charts, funnel /
    leaderboard; server-side table of the tab. Empty period → empty state above the table.
--}}
@php
    /** @var \App\Modules\Reporting\DTOs\ReportFilterData $filters */
    /** @var \App\Modules\Reporting\DTOs\ReportSummary $summary */
    $query = $filters->query();
    $period = \App\Modules\Reporting\Support\ReportFormat::period($filters);
    $tabItems = [];
    foreach (\App\Modules\Reporting\Enums\ReportTab::cases() as $item) {
        $tabItems[$item->value] = ['label' => $item->label(), 'href' => route('admin.reports.show', ['tab' => $item->value, ...$query])];
    }
    $canExport = (bool) auth('admin')->user()?->can('reports.export');
    $number = fn ($value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-layouts.panel surface="admin" :title="__('reporting::reports.title').' · '.$tab->label()">
    <div class="grid gap-6 *:min-w-0">
        <x-ui::page-header
            :title="__('reporting::reports.title')"
            :subtitle="__('reporting::reports.subtitle', ['period' => $period])"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('reporting::reports.title') => route('admin.reports.index'), $tab->label() => null]"
        >
            <x-slot:actions>
                <x-ui::filters-bar :search="false" reload :chips="false" class="w-full lg:w-auto">
                    <x-ui::daterange
                        name="period"
                        from-name="from"
                        to-name="to"
                        :label="__('reporting::reports.filters.period')"
                        label-hidden
                        :from="$filters->start()->toDateString()"
                        :to="$filters->end()->toDateString()"
                        :max="now()->toDateString()"
                        filter
                        compact
                        class="w-full sm:w-[260px]"
                    />
                    <x-ui::select
                        name="tenant_id"
                        :label="__('reporting::reports.filters.store_label')"
                        label-hidden
                        :options="$selectedStore"
                        :selected="$filters->tenantId"
                        :placeholder="__('reporting::reports.filters.store')"
                        :ajax-url="route('admin.reports.stores')"
                        allow-clear
                        data-filter
                        :data-filter-label="__('reporting::reports.filters.store_label')"
                        class="w-full sm:w-48"
                    />
                    @if ($tab->filtersByCountry())
                        <x-ui::select
                            name="country_id"
                            :label="__('reporting::reports.filters.country_label')"
                            label-hidden
                            :options="$countries"
                            :selected="$filters->countryId"
                            :placeholder="__('reporting::reports.filters.country')"
                            allow-clear
                            data-filter
                            :data-filter-label="__('reporting::reports.filters.country_label')"
                            class="w-full sm:w-44"
                        />
                    @endif
                    @if ($canExport)
                        <x-slot:end>
                            <x-ui::button icon="download" :href="route('admin.reports.export', ['tab' => $tab->value, ...$query])">{{ __('reporting::reports.export') }}</x-ui::button>
                        </x-slot:end>
                    @endif
                </x-ui::filters-bar>
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="report-tabs" :active="$tab->value" :label="__('reporting::reports.tabs_label')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @unless ($tab->filtersByCountry())
            <p class="flex items-center gap-2 text-sm text-ink-secondary"><x-ui::icon name="info" :size="16" />{{ __('reporting::reports.filters.country_not_applicable') }}</p>
        @endunless

        @if ($summary->isEmpty())
            <x-ui::card>
                <x-ui::empty-state variant="no-data" icon="chart" :title="__('reporting::reports.empty_title')" :description="__('reporting::reports.empty_description')" />
            </x-ui::card>
        @else
            @include('reporting::admin.reports._summary')
        @endif

        <section class="grid gap-3" aria-labelledby="report-table-title">
            <h2 id="report-table-title" class="text-base font-semibold text-ink">{{ $tableTitle }}</h2>
            <x-ui::datatable
                id="report-table"
                :url="route('admin.reports.data', ['tab' => $tab->value, ...$query])"
                :columns="$columns"
                :order="[$order]"
                :empty-title="__('reporting::reports.table_empty_title')"
                :empty-description="__('reporting::reports.table_empty_description')"
            />
        </section>
    </div>
</x-layouts.panel>
