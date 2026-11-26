{{-- Period filter (page reload, URL synced) + CSV export of a vendor analytics page. --}}
<x-ui::filters-bar :search="false" reload :chips="false" class="w-full lg:w-auto">
    <x-ui::daterange
        name="period"
        from-name="from"
        to-name="to"
        :label="__('reporting::vendor_analytics.period')"
        label-hidden
        :from="$filters->start()->toDateString()"
        :to="$filters->end()->toDateString()"
        :max="now()->toDateString()"
        filter
        compact
        class="w-full sm:w-[260px]"
    />
    <x-slot:end>
        <x-ui::button icon="download" :href="$exportUrl">{{ __('reporting::vendor_analytics.export') }}</x-ui::button>
    </x-slot:end>
</x-ui::filters-bar>
