{{--
    Admin › Affiliates › Accounts (ADM-060, Figma Admin Panel 74:66931): tabs all /
    vendor-linked / independent / pending / suspended with counts, search, type / status
    filters, row view / edit (ADM-060a) / pay out (ADM-060b), Program settings (ADM-060c).
--}}
@php
    $admin = auth('admin')->user();
    $tabItems = [];
    foreach (['all', 'vendor', 'independent', 'pending', 'suspended'] as $key) {
        $tabItems[$key] = ['label' => __('affiliates::admin.accounts.tabs.'.$key), 'href' => route('admin.affiliates.index', $key === 'all' ? [] : ['tab' => $key]), 'count' => number_format($counts[$key])];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('affiliates::admin.accounts.title')" data-page="admin-affiliates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('affiliates::admin.accounts.title')" :subtitle="__('affiliates::admin.accounts.subtitle')"
            :breadcrumbs="[__('navigation.sections.finance_growth') => null, __('navigation.admin.affiliates') => null, __('navigation.admin.affiliate_accounts') => null]">
            @if ($admin?->can('affiliates.settings.manage'))
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="settings" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.settings.edit')">{{ __('affiliates::admin.settings.button') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="affiliate-tabs" :active="$tab" :label="__('affiliates::admin.accounts.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="affiliates"
            :url="route('admin.affiliates.data')"
            :columns="[
                ['data' => 'affiliate', 'title' => __('affiliates::admin.accounts.columns.affiliate')],
                ['data' => 'type', 'title' => __('affiliates::admin.accounts.columns.type')],
                ['data' => 'clicks', 'title' => __('affiliates::admin.accounts.columns.clicks'), 'align' => 'end'],
                ['data' => 'conversions', 'title' => __('affiliates::admin.accounts.columns.conversions'), 'align' => 'end'],
                ['data' => 'earned', 'title' => __('affiliates::admin.accounts.columns.earned'), 'align' => 'end'],
                ['data' => 'balance', 'title' => __('affiliates::admin.accounts.columns.balance'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('affiliates::admin.accounts.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('affiliates::admin.accounts.empty_title')"
            :empty-description="__('affiliates::admin.accounts.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="affiliates" :search-placeholder="__('affiliates::admin.accounts.search')">
                    @if (in_array($tab, ['vendor', 'independent'], true))
                        <input type="hidden" name="type" value="{{ $tab }}" data-filter>
                    @else
                        <x-ui::select name="type" :label="__('affiliates::admin.accounts.columns.type')" label-hidden :options="$types" :placeholder="__('affiliates::admin.accounts.columns.type')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::admin.accounts.columns.type')" class="w-40" />
                    @endif
                    @if (in_array($tab, ['pending', 'suspended'], true))
                        <input type="hidden" name="status" value="{{ $tab }}" data-filter>
                    @else
                        <x-ui::select name="status" :label="__('affiliates::admin.accounts.columns.status')" label-hidden :options="$statuses" :placeholder="__('affiliates::admin.accounts.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('affiliates::admin.accounts.columns.status')" class="w-40" />
                    @endif
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="affiliate-modal" :title="__('affiliates::admin.accounts.title')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
