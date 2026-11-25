{{--
    Admin › Finance › Payouts platform → store (ADM-052, Figma Admin Panel 74:61743): tabs
    all / scheduled / processing / paid / cancelled, search, method / store filters, row
    view / edit (ADM-052b) / cancel, Create payout (ADM-052a).
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('finance.payouts.manage');
    $tabItems = ['all' => ['label' => __('billing::finance.payouts.tabs.all'), 'href' => route('admin.finance.payouts.index')]];
    foreach (\App\Modules\Billing\Enums\PayoutStatus::cases() as $status) {
        $tabItems[$status->value] = ['label' => $status->label(), 'href' => route('admin.finance.payouts.index', ['status' => $status->value])] + ($status->isOpen() ? ['count' => number_format($stats[$status->value])] : []);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('billing::finance.payouts.title')" data-page="admin-finance">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('billing::finance.payouts.title')" :subtitle="__('billing::finance.payouts.subtitle')"
            :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::finance.payouts.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" :href="route('admin.finance.payouts.create')">{{ __('billing::finance.payouts.create') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="payout-tabs" :active="$active" :label="__('billing::finance.payouts.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="payouts"
            :url="route('admin.finance.payouts.data')"
            :columns="[
                ['data' => 'payout', 'title' => __('billing::finance.payouts.columns.payout')],
                ['data' => 'store', 'title' => __('billing::finance.payouts.columns.store'), 'orderable' => false],
                ['data' => 'method', 'title' => __('billing::finance.payouts.columns.method')],
                ['data' => 'amount', 'title' => __('billing::finance.payouts.columns.amount'), 'align' => 'end'],
                ['data' => 'reference', 'title' => __('billing::finance.payouts.columns.reference')],
                ['data' => 'status', 'title' => __('billing::finance.payouts.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('billing::finance.payouts.empty_title')"
            :empty-description="__('billing::finance.payouts.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="payouts" :search-placeholder="__('billing::finance.payouts.search')">
                    @if ($active !== 'all')
                        <input type="hidden" name="status" value="{{ $active }}" data-filter>
                    @endif
                    <x-ui::select name="method" :label="__('billing::finance.payouts.fields.method')" label-hidden :options="$methods" :placeholder="__('billing::finance.payouts.fields.method')" :search="false" allow-clear data-filter :data-filter-label="__('billing::finance.payouts.fields.method')" class="w-44" />
                    <x-ui::select name="tenant_id" :label="__('billing::finance.filters.store')" label-hidden :options="[]" :placeholder="__('billing::finance.filters.store')" :ajax-url="route('admin.finance.payouts.stores')" allow-clear data-filter :data-filter-label="__('billing::finance.filters.store')" class="w-48" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="payout-modal" :title="__('billing::finance.payouts.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
