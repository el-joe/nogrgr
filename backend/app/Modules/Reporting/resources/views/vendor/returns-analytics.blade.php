{{--
    Vendor › Orders › Return analytics (VEN-024, Figma Vendor Panel 84:105181): Refunds / Return
    analytics tabs, period filter + CSV export, KPI cards, return reasons, refunds over time and
    the refunds requested in the period.
--}}
@php
    /** @var \App\Modules\Reporting\DTOs\ReportFilterData $filters */
    $query = ['from' => $filters->start()->toDateString(), 'to' => $filters->end()->toDateString()];
    $staff = auth('vendor')->user();
    $tabItems = [];
    if ($staff?->can('refunds.view')) {
        $tabItems['refunds'] = ['label' => __('reporting::vendor_analytics.returns_tabs.refunds'), 'href' => vendor_route('sales.refunds.index')];
    }
    $tabItems['analytics'] = ['label' => __('reporting::vendor_analytics.returns_tabs.analytics'), 'href' => vendor_route('sales.returns.analytics', $query)];
@endphp
<x-vendor::layout :title="__('reporting::vendor_analytics.returns_title')" page="vendor-orders">
    <div class="grid gap-6 *:min-w-0">
        <x-ui::page-header
            :title="__('reporting::vendor_analytics.returns_title')"
            :subtitle="__('reporting::vendor_analytics.subtitles.returns', ['period' => \App\Modules\Reporting\Support\ReportFormat::period($filters)])"
            :breadcrumbs="[__('navigation.vendor.orders') => vendor_route('sales.orders.index'), __('reporting::vendor_analytics.returns_title') => null]"
        >
            <x-slot:actions>
                @include('reporting::vendor._filters', ['exportUrl' => vendor_route('sales.returns.analytics.export', $query)])
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="returns-analytics-tabs" active="analytics" :label="__('reporting::vendor_analytics.returns_title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @include('reporting::vendor._body', ['dataUrl' => vendor_route('sales.returns.analytics.data', $query)])
    </div>
</x-vendor::layout>
