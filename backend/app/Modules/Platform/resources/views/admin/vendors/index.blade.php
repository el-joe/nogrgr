{{--
    Admin › Vendors › All vendors (ADM-040, Figma 72:47934): status tabs with counts,
    Export (keeps the filters) and Create vendor, filters (search, plan, country,
    status) and the server-side table: store, owner, plan, profit %, status, actions.
    The KPI-like GMV column needs orders (P9) and is not shown.
--}}
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('vendors.manage');
    $canExport = (bool) $admin?->can('vendors.export');
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $active = 'all';
    if (request()->query('subscription_status') === 'trial') {
        $active = 'trial';
    } elseif (in_array(request()->query('status'), ['active', 'suspended', 'pending'], true)) {
        $active = request()->query('status');
    }
    $tabs = ['all' => ['label' => __('platform::vendors.tabs.all'), 'count' => $counts['all'] ?? 0, 'href' => route('admin.vendors.index')]];
    $tabs['active'] = ['label' => __('platform::vendors.tabs.active'), 'count' => $counts['active'] ?? 0, 'href' => route('admin.vendors.index', ['status' => 'active'])];
    $tabs['trial'] = ['label' => __('platform::vendors.tabs.trial'), 'count' => $counts['trial'] ?? 0, 'href' => route('admin.vendors.index', ['subscription_status' => 'trial'])];
    $tabs['suspended'] = ['label' => __('platform::vendors.tabs.suspended'), 'count' => $counts['suspended'] ?? 0, 'href' => route('admin.vendors.index', ['status' => 'suspended'])];
    $tabs['pending'] = ['label' => __('platform::vendors.tabs.pending'), 'count' => $counts['pending'] ?? 0, 'href' => route('admin.vendors.index', ['status' => 'pending'])];
    $waiting = ($counts['pending'] ?? 0) + ($counts['provisioning'] ?? 0) + ($counts['failed'] ?? 0);
@endphp
<x-layouts.panel surface="admin" :title="__('platform::vendors.title')" data-page="admin-vendors">
    <x-ui::page-header
        :title="__('platform::vendors.title')"
        :subtitle="__('platform::vendors.subtitle', ['active' => $number($counts['active'] ?? 0), 'pending' => $number($waiting)])"
        :breadcrumbs="[__('navigation.sections.vendors') => null, __('platform::vendors.all_title') => null]"
    >
        <x-slot:actions>
            @if ($canExport)
                <x-ui::button variant="secondary" icon="download" :href="route('admin.vendors.export')" data-export-for="vendors">{{ __('platform::vendors.export') }}</x-ui::button>
            @endif
            @if ($canManage)
                <x-ui::button icon="plus" :href="route('admin.vendors.create')">{{ __('platform::vendors.create_vendor') }}</x-ui::button>
            @endif
        </x-slot:actions>
        <x-slot:tabs>
            <x-ui::tabs id="vendor-tabs" :active="$active" :label="__('platform::vendors.title')" :items="$tabs" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="vendors"
        :url="route('admin.vendors.data')"
        :columns="[
            ['data' => 'store', 'title' => __('platform::vendors.columns.store'), 'searchable' => false],
            ['data' => 'owner', 'title' => __('platform::vendors.columns.owner'), 'searchable' => false],
            ['data' => 'plan', 'title' => __('platform::vendors.columns.plan'), 'orderable' => false, 'searchable' => false],
            ['data' => 'profit_percent_bps', 'title' => __('platform::vendors.columns.profit'), 'align' => 'end', 'searchable' => false],
            ['data' => 'status', 'title' => __('platform::vendors.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('platform::vendors.empty_title')"
        :empty-description="__('platform::vendors.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="vendors" :search-placeholder="__('platform::vendors.search')">
                <x-ui::select name="plan_id" :label="__('platform::vendors.filters.plan')" label-hidden :options="$plans" :placeholder="__('platform::vendors.filters.plan')" allow-clear data-filter :data-filter-label="__('platform::vendors.filters.plan')" class="w-40" />
                <x-ui::select name="country_id" :label="__('platform::vendors.filters.country')" label-hidden :options="$countries" :placeholder="__('platform::vendors.filters.country')" allow-clear data-filter :data-filter-label="__('platform::vendors.filters.country')" class="w-44" />
                <x-ui::select name="status" :label="__('platform::vendors.filters.status')" label-hidden :options="$statuses" :placeholder="__('platform::vendors.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::vendors.filters.status')" class="w-40" />
                <x-ui::select name="subscription_status" :label="__('platform::vendors.filters.subscription')" label-hidden :options="['trial' => __('platform::vendors.tabs.trial')]" :placeholder="__('platform::vendors.filters.subscription')" :search="false" allow-clear data-filter :data-filter-label="__('platform::vendors.filters.subscription')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.vendors.create')">{{ __('platform::vendors.create_vendor') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
