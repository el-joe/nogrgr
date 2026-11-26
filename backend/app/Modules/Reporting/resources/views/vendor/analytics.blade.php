{{--
    Vendor › Analytics (VEN-045; tabs 045a Orders · Figma 12:16223, 045c Customer lifetime value ·
    12:16815, 045b Shipping / 045d Product profitability · 87:108835). Period filter + CSV export,
    pill tabs, KPI cards, charts, proportions / ranking and the tab's server-side table.
--}}
@php
    /** @var \App\Modules\Reporting\DTOs\ReportFilterData $filters */
    $query = ['from' => $filters->start()->toDateString(), 'to' => $filters->end()->toDateString()];
    $tabItems = [];
    foreach (\App\Modules\Reporting\Enums\VendorAnalyticsTab::analyticsTabs() as $item) {
        $tabItems[$item->value] = ['label' => $item->label(), 'href' => vendor_route('analytics', ['tab' => $item->value, ...$query])];
    }
@endphp
<x-vendor::layout :title="__('reporting::vendor_analytics.title').' · '.$tab->label()">
    <div class="grid gap-6 *:min-w-0">
        <x-ui::page-header
            :title="__('reporting::vendor_analytics.title')"
            :subtitle="__('reporting::vendor_analytics.subtitles.'.$tab->value, ['period' => \App\Modules\Reporting\Support\ReportFormat::period($filters)])"
            :breadcrumbs="[__('reporting::vendor_analytics.title') => null]"
        >
            <x-slot:actions>
                @include('reporting::vendor._filters', ['exportUrl' => vendor_route('analytics.export', ['tab' => $tab->value, ...$query])])
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="analytics-tabs" :active="$tab->value" :label="__('reporting::vendor_analytics.tabs_label')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @include('reporting::vendor._body', ['dataUrl' => vendor_route('analytics.data', ['tab' => $tab->value, ...$query])])
    </div>
</x-vendor::layout>
