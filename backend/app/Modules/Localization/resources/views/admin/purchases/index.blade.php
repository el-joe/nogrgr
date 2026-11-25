{{--
    Admin › Localization › Languages › Purchases (ADM-094, Figma 79:83875): the third tab
    of the Languages page. Language and AI translation add-on purchases of every store
    (read from Billing) with search, type / status / gateway filters and a detail page.
--}}
@php
    $tabs = [];
    if (auth('admin')->user()?->can('localization.languages.view')) {
        $tabs['active'] = ['label' => __('localization::purchases.tab_active'), 'count' => $counts['active'], 'href' => route('admin.localization.languages.index')];
        $tabs['all'] = ['label' => __('localization::purchases.tab_all'), 'count' => $counts['all'], 'href' => route('admin.localization.languages.index', ['tab' => 'all'])];
    }
    $tabs['purchases'] = ['label' => __('localization::purchases.tab'), 'count' => $counts['purchases'], 'href' => route('admin.localization.purchases.index')];
@endphp
<x-layouts.panel surface="admin" :title="__('localization::purchases.title')">
    <x-ui::page-header
        :title="__('localization::purchases.title')"
        :subtitle="__('localization::purchases.subtitle', ['count' => $counts['purchases']])"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::purchases.tab') => null]"
    >
        <x-slot:tabs>
            <x-ui::tabs id="languages-tabs" active="purchases" :label="__('localization::purchases.title')" :items="$tabs" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="language-purchases"
        :url="route('admin.localization.purchases.data')"
        :columns="[
            ['data' => 'store', 'title' => __('localization::purchases.columns.store'), 'orderable' => false],
            ['data' => 'purchase', 'title' => __('localization::purchases.columns.purchase'), 'orderable' => false],
            ['data' => 'amount_minor', 'title' => __('localization::purchases.columns.amount'), 'align' => 'end'],
            ['data' => 'created_at', 'title' => __('localization::purchases.columns.date')],
            ['data' => 'gateway', 'title' => __('localization::purchases.columns.gateway'), 'orderable' => false],
            ['data' => 'invoice_number', 'title' => __('localization::purchases.columns.invoice'), 'orderable' => false],
            ['data' => 'status', 'title' => __('localization::purchases.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'desc']]"
        :empty-title="__('localization::purchases.empty_title')"
        :empty-description="__('localization::purchases.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="language-purchases" :search-placeholder="__('localization::purchases.search')">
                <x-ui::select name="type" :label="__('localization::purchases.columns.type')" label-hidden :options="\App\Modules\Billing\Enums\AddonType::options()" :placeholder="__('localization::purchases.columns.type')" :search="false" allow-clear data-filter :data-filter-label="__('localization::purchases.columns.type')" class="w-44" />
                <x-ui::select name="status" :label="__('localization::purchases.columns.status')" label-hidden :options="\App\Modules\Billing\Enums\AddonPurchaseStatus::options()" :placeholder="__('localization::purchases.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('localization::purchases.columns.status')" class="w-40" />
                <x-slot:more>
                    <x-ui::select name="gateway" :label="__('localization::purchases.columns.gateway')" :options="\App\Modules\Billing\Enums\PaymentGateway::options()" :placeholder="__('localization::purchases.columns.gateway')" :search="false" allow-clear data-filter :data-filter-label="__('localization::purchases.columns.gateway')" />
                </x-slot:more>
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
