{{-- Summary (or the empty state of a quiet period) and the server-side table of a vendor analytics tab. --}}
@if ($summary->isEmpty())
    <x-ui::card>
        <x-ui::empty-state variant="no-data" icon="chart" :title="__('reporting::vendor_analytics.empty_title')" :description="__('reporting::vendor_analytics.empty_description')" />
    </x-ui::card>
@else
    @include('reporting::admin.reports._summary', [
        'funnelTitle' => __('reporting::vendor_analytics.funnel_title'),
        'leadersTitle' => __('reporting::vendor_analytics.leaders_title'),
        'plainKpis' => true,
    ])
@endif

<section class="grid gap-4 rounded-[12px] bg-surface p-4 sm:p-6" aria-labelledby="analytics-table-title" data-reveal="fade-up">
    <h2 id="analytics-table-title" class="text-2xl font-semibold leading-[1.5] text-[var(--vnd-card-title)]">{{ $tableTitle }}</h2>
    <x-ui::datatable
        id="analytics-table"
        :url="$dataUrl"
        :columns="$columns"
        :order="[$order]"
        :empty-title="__('reporting::vendor_analytics.table_empty_title')"
        :empty-description="__('reporting::vendor_analytics.table_empty_description')"
    />
</section>
