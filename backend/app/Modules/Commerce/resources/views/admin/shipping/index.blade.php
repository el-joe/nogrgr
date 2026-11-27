{{--
    Admin › Sales › Shipping queue (ADM-022, Figma Admin Panel 70:34839): catalog orders the
    platform ships — tabs to ship / in transit / delayed / delivered today, search, company /
    branch / country filters, row selection with "Mark shipped", detail + status modal.
--}}
@php
    $tabItems = [];
    foreach ($tabs as $key => $tab) {
        $tabItems[$key] = ['label' => $tab['label'], 'count' => number_format($tab['count']), 'href' => route('admin.shipping-queue.index', ['tab' => $key])];
    }
    $canManage = (bool) auth('admin')->user()?->can('shipping-queue.manage');
    $columns = [];
    if ($canManage && $active === 'to_ship') {
        $columns[] = ['data' => 'select', 'title' => '', 'orderable' => false, 'searchable' => false];
    }
    $columns = [...$columns,
        ['data' => 'order', 'title' => __('commerce::fulfilment.queue.columns.order')],
        ['data' => 'customer', 'title' => __('commerce::fulfilment.queue.columns.customer')],
        ['data' => 'company', 'title' => __('commerce::fulfilment.queue.columns.company')],
        ['data' => 'branch', 'title' => __('commerce::fulfilment.queue.columns.branch')],
        ['data' => 'ship_by', 'title' => __('commerce::fulfilment.queue.columns.ship_by')],
        ['data' => 'status', 'title' => __('commerce::fulfilment.queue.columns.status')],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ];
@endphp
<x-layouts.panel surface="admin" :title="__('commerce::fulfilment.queue.title')" data-page="admin-orders">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('commerce::fulfilment.queue.title')"
            :subtitle="__('commerce::fulfilment.queue.subtitle')"
            :breadcrumbs="[__('navigation.sections.sales') => null, __('commerce::fulfilment.queue.title') => null]"
        >
            @if ($canManage && $active === 'to_ship')
                <x-slot:actions>
                    <form id="shipping-bulk-form" method="POST" action="{{ route('admin.shipping-queue.bulk-ship') }}" data-ajax novalidate data-confirm="{{ __('commerce::fulfilment.queue.bulk_confirm') }}" data-confirm-tone="primary">
                        @csrf
                        <x-ui::button type="submit" icon="truck" data-bulk-submit disabled>{{ __('commerce::fulfilment.queue.mark_shipped') }} (<span data-bulk-count>0</span>)</x-ui::button>
                    </form>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="shipping-queue-tabs" :active="$active" :label="__('commerce::fulfilment.queue.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($canManage && $active === 'to_ship')
            <label class="flex items-center gap-2 text-sm text-ink-secondary">
                <input type="checkbox" class="ui-checkbox" data-select-all>
                {{ __('commerce::fulfilment.queue.select_all') }}
            </label>
        @endif

        <x-ui::datatable
            id="shipping-queue"
            :url="route('admin.shipping-queue.data')"
            :columns="$columns"
            :order="[[count($columns) - 3, 'asc']]"
            :empty-title="__('commerce::fulfilment.queue.empty_title')"
            :empty-description="__('commerce::fulfilment.queue.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="shipping-queue" :search-placeholder="__('commerce::fulfilment.queue.search')">
                    <input type="hidden" name="tab" value="{{ $active }}" data-filter>
                    <x-ui::select name="company_id" :label="__('commerce::fulfilment.fields.company')" label-hidden :options="$companies" :placeholder="__('commerce::fulfilment.fields.company')" allow-clear data-filter :data-filter-label="__('commerce::fulfilment.fields.company')" class="w-44" />
                    <x-ui::select name="branch_id" :label="__('commerce::fulfilment.fields.branch')" label-hidden :options="$branches" :placeholder="__('commerce::fulfilment.fields.branch')" allow-clear data-filter :data-filter-label="__('commerce::fulfilment.fields.branch')" class="w-40" />
                    <x-ui::select name="country_id" :label="__('commerce::orders_admin.filters.country')" label-hidden :options="$countries" :placeholder="__('commerce::orders_admin.filters.country')" allow-clear data-filter :data-filter-label="__('commerce::orders_admin.filters.country')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="shipping-modal" :title="__('commerce::fulfilment.status_modal.title', ['number' => ''])">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
