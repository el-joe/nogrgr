{{--
    Vendor › Finance › Money flow (VEN-062 / 063 / 064, Figma Vendor Panel 88:111108):
    tabs Purchases (owed) · Settlement payments (vendor → platform) · Payouts received
    (platform → you), balance summary, one server-side table per tab.
--}}
@php
    $money = fn (int $minor) => \App\Shared\Money\Money::ofMinor($minor)->format();
    $staff = auth('vendor')->user();
    $tabs = [];
    if ($staff?->can('finance.purchases.view')) {
        $tabs['purchases'] = ['label' => __('billing::finance.money_flow.tabs.purchases'), 'href' => vendor_route('finance.purchases.index'), 'count' => number_format($summary['open_orders']->count())];
    }
    if ($staff?->can('finance.settlements.view')) {
        $tabs['settlements'] = ['label' => __('billing::finance.money_flow.tabs.settlements'), 'href' => vendor_route('finance.settlements.index')];
    }
    if ($staff?->can('finance.payouts.view')) {
        $tabs['payouts'] = ['label' => __('billing::finance.money_flow.tabs.payouts'), 'href' => vendor_route('finance.payouts.index')];
    }
    $tables = [
        'purchases' => [
            'url' => vendor_route('finance.purchases.data'),
            'title' => __('billing::finance.money_flow.purchases_title'),
            'columns' => [
                ['data' => 'order', 'title' => __('billing::finance.money_flow.columns.order')],
                ['data' => 'accrued', 'title' => __('billing::finance.money_flow.columns.accrued'), 'align' => 'end', 'orderable' => false],
                ['data' => 'settled', 'title' => __('billing::finance.money_flow.columns.settled'), 'align' => 'end', 'orderable' => false],
                ['data' => 'owed', 'title' => __('billing::finance.money_flow.columns.owed'), 'align' => 'end'],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ],
            'order' => [[0, 'asc']],
            'search' => __('billing::finance.money_flow.search_order'),
        ],
        'settlements' => [
            'url' => vendor_route('finance.settlements.data'),
            'title' => __('billing::finance.money_flow.settlements_title'),
            'columns' => [
                ['data' => 'settlement', 'title' => __('billing::finance.settlements.columns.settlement')],
                ['data' => 'orders', 'title' => __('billing::finance.settlements.columns.orders'), 'align' => 'end'],
                ['data' => 'gateway', 'title' => __('billing::finance.settlements.columns.gateway'), 'orderable' => false],
                ['data' => 'amount', 'title' => __('billing::finance.settlements.columns.amount'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('billing::finance.settlements.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ],
            'order' => [[0, 'desc']],
            'search' => __('billing::finance.settlements.search_reference'),
        ],
        'payouts' => [
            'url' => vendor_route('finance.payouts.data'),
            'title' => __('billing::finance.money_flow.payouts_title'),
            'columns' => [
                ['data' => 'payout', 'title' => __('billing::finance.payouts.columns.payout')],
                ['data' => 'method', 'title' => __('billing::finance.payouts.columns.method')],
                ['data' => 'reference', 'title' => __('billing::finance.payouts.columns.reference')],
                ['data' => 'amount', 'title' => __('billing::finance.payouts.columns.amount'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('billing::finance.payouts.columns.status')],
            ],
            'order' => [[0, 'desc']],
            'search' => __('billing::finance.payouts.search_reference'),
        ],
    ];
    $table = $tables[$tab];
    $empty = [
        'purchases' => [__('billing::finance.money_flow.empty.purchases_title'), __('billing::finance.money_flow.empty.purchases')],
        'settlements' => [__('billing::finance.money_flow.empty.settlements_title'), __('billing::finance.money_flow.empty.settlements')],
        'payouts' => [__('billing::finance.money_flow.empty.payouts_title'), __('billing::finance.money_flow.empty.payouts')],
    ][$tab];
@endphp
<x-vendor::layout :title="__('billing::finance.money_flow.title')" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.money_flow.title')" :subtitle="__('billing::finance.money_flow.subtitle')"
            :breadcrumbs="[__('billing::finance.money_flow.crumb') => null, $tables[$tab]['title'] => null]">
            @if ($tab === 'purchases')
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="download" :href="vendor_route('finance.purchases.export')">{{ __('billing::finance.export') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="money-flow-tabs" :active="$tab" :label="__('billing::finance.money_flow.title')" :items="$tabs" />
            </x-slot:tabs>
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.money_flow.owed_to_platform') }}</p>
                <p class="mt-1 text-xl font-semibold text-warning" dir="ltr">{{ $money($summary['receivable']) }}</p>
            </div>
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.money_flow.owed_to_you') }}</p>
                <p class="mt-1 text-xl font-semibold text-success" dir="ltr">{{ $money($summary['payable']) }}</p>
            </div>
            <div class="rounded-card bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ __('billing::finance.money_flow.payouts_scheduled') }}</p>
                <p class="mt-1 text-xl font-semibold text-ink" dir="ltr">{{ $money($summary['open_payouts']) }}</p>
            </div>
        </div>

        <x-ui::datatable
            :id="'money-flow-'.$tab"
            :url="$table['url']"
            :columns="$table['columns']"
            :order="$table['order']"
            :empty-title="$empty[0]"
            :empty-description="$empty[1]"
        >
            <x-slot:filters>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-ink">{{ $table['title'] }}</h2>
                    <x-ui::filters-bar :target="'money-flow-'.$tab" :search-placeholder="$table['search']" />
                </div>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    @if ($tab === 'settlements')
        <x-ui::modal id="settlement-modal" :title="__('billing::finance.settlements.title')">
            @include('commerce::admin.partials.remote-states')
        </x-ui::modal>
    @endif
</x-vendor::layout>
