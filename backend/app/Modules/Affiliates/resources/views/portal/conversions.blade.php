{{--
    Affiliate › Conversions (AFF-011, Figma Affiliate & Owner 121:101191 · mobile 121:103181):
    totals and the server-side list of the affiliate's conversions, filter by status.
    Inputs: $overview, $statuses.
--}}
<x-layouts.panel surface="affiliate" :title="__('affiliates::panel.conversions.title')" data-page="affiliate-conversions">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::panel.conversions.title')" :subtitle="__('affiliates::panel.conversions.subtitle')" />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui::kpi-card icon="check-circle" :label="__('affiliates::panel.links.conversions_total')" :value="\Illuminate\Support\Number::format($overview['conversionsTotal'], locale: \App\Shared\Support\Locale::numberLocale())" />
            <x-ui::kpi-card icon="clock" :label="__('affiliates::panel.dashboard.pending_commission')" :value="$overview['pendingCommission']->format()" />
            <x-ui::kpi-card icon="award" :label="__('affiliates::panel.conversions.earned')" :value="$overview['earned']->format()" />
        </div>

        <x-ui::datatable
            id="conversions"
            :url="route('affiliate.conversions.data')"
            :columns="[
                ['data' => 'conversion', 'title' => __('affiliates::panel.conversions.conversion')],
                ['data' => 'store', 'title' => __('affiliates::panel.conversions.store'), 'orderable' => false],
                ['data' => 'sale', 'title' => __('affiliates::panel.conversions.sale'), 'align' => 'end'],
                ['data' => 'commission', 'title' => __('affiliates::panel.conversions.commission'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('affiliates::panel.conversions.status')],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('affiliates::panel.conversions.empty_title')"
            :empty-description="__('affiliates::panel.conversions.empty_text')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="conversions" :search="false">
                    <x-ui::select name="status" :label="__('affiliates::panel.conversions.status')" label-hidden :options="$statuses" :placeholder="__('affiliates::panel.conversions.all_statuses')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::panel.conversions.status')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
