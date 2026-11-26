{{--
    Vendor › Analytics (VEN-045). Every tab — 045a Orders · Figma 12:16223, 045c Customer lifetime
    value · 12:16815, 045b Shipping and 045d Product profitability — uses the client 12:16223
    layout (decision #101): Bold 32 title + period filter / Download report, swipeable pill tabs,
    the tab title (Bold 24) + subtitle, KPI cards, chart cards and the table card.
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
            :breadcrumbs="[__('reporting::vendor_analytics.title') => null]"
        >
            <x-slot:actions>
                @include('reporting::vendor._filters', ['exportUrl' => vendor_route('analytics.export', ['tab' => $tab->value, ...$query])])
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="analytics-tabs" variant="pill" :active="$tab->value" :label="__('reporting::vendor_analytics.tabs_label')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <header class="grid gap-1" data-reveal="fade-up">
            <h2 class="text-2xl font-bold leading-[1.5] text-[var(--vnd-title)]">{{ $tab->label() }}</h2>
            <p class="text-sm text-[var(--vnd-text-sub)]">{{ __('reporting::vendor_analytics.subtitles.'.$tab->value, ['period' => \App\Modules\Reporting\Support\ReportFormat::period($filters)]) }}</p>
        </header>

        @include('reporting::vendor._body', ['dataUrl' => vendor_route('analytics.data', ['tab' => $tab->value, ...$query])])
    </div>
</x-vendor::layout>
