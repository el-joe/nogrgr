{{-- Summary (or the empty state of a quiet period) and the server-side table of a vendor analytics tab. --}}
@if ($summary->isEmpty())
    <x-ui::card>
        <x-ui::empty-state variant="no-data" icon="chart" :title="__('reporting::vendor_analytics.empty_title')" :description="__('reporting::vendor_analytics.empty_description')" />
    </x-ui::card>
@else
    @include('reporting::admin.reports._summary', [
        'funnelTitle' => __('reporting::vendor_analytics.funnel_title'),
        'leadersTitle' => __('reporting::vendor_analytics.leaders_title'),
    ])
@endif

<section class="grid gap-3" aria-labelledby="analytics-table-title">
    <h2 id="analytics-table-title" class="text-base font-semibold text-ink">{{ $tableTitle }}</h2>
    <x-ui::datatable
        id="analytics-table"
        :url="$dataUrl"
        :columns="$columns"
        :order="[$order]"
        :empty-title="__('reporting::vendor_analytics.table_empty_title')"
        :empty-description="__('reporting::vendor_analytics.table_empty_description')"
    />
</section>
