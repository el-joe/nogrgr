{{--
    Vendor › Requests (VEN-050 hub, 050a product / 050d manufacturing / 050g brand lists,
    Figma 12:15910): kind tabs with unread badges, Total / Pending / Completed cards and
    the server-side list (search, status). "Add request" opens the product / brand modal
    (VEN-050b / 050h) or the manufacturing page (VEN-050e); manufacturing has the CSV report.
--}}
@php
    $key = $kind->value;
    $createUrl = vendor_route('requests.'.$key.'.create');
@endphp
<x-vendor::layout :title="__('catalog::vendor_requests.pages.'.$key.'.title')" page="vendor-requests">
    <div class="grid gap-6" data-requests-list>
        <x-ui::page-header
            :title="__('catalog::vendor_requests.pages.'.$key.'.title')"
            :subtitle="__('catalog::vendor_requests.pages.'.$key.'.subtitle')"
            :breadcrumbs="[__('catalog::vendor_requests.title') => vendor_route('requests.index'), __('catalog::vendor_requests.tabs.'.$key) => null]"
        >
            <x-slot:actions>
                @if ($kind === \App\Modules\Catalog\Enums\RequestKind::Manufacturing)
                    <x-ui::button variant="secondary" icon="download" :href="vendor_route('requests.manufacturing.export')" data-export-link>{{ __('catalog::vendor_requests.list.export') }}</x-ui::button>
                @endif
                @if ($canManage)
                    @if ($kind === \App\Modules\Catalog\Enums\RequestKind::Manufacturing)
                        <x-ui::button icon="plus" :href="$createUrl">{{ __('catalog::vendor_requests.new.'.$key) }}</x-ui::button>
                    @else
                        <x-ui::button icon="plus" data-remote-modal="vendor-request-modal" :data-url="$createUrl">{{ __('catalog::vendor_requests.new.'.$key) }}</x-ui::button>
                    @endif
                @endif
            </x-slot:actions>
            @if (count($tabs) > 1)
                <x-slot:tabs>
                    <x-ui::tabs id="vendor-requests-tabs" :active="$key" :label="__('catalog::vendor_requests.title')" :items="$tabs" />
                </x-slot:tabs>
            @endif
        </x-ui::page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui::kpi-card :label="__('catalog::vendor_requests.stats.total')" :value="number_format($stats['total'])" icon="mail" :comparison="__('catalog::vendor_requests.stats.total_hint', ['kind' => mb_strtolower(__('catalog::vendor_requests.tabs.'.$key))])" />
            <x-ui::kpi-card :label="__('catalog::vendor_requests.stats.open')" :value="number_format($stats['open'])" icon="clock" :comparison="__('catalog::vendor_requests.stats.open_hint')" />
            <x-ui::kpi-card :label="__('catalog::vendor_requests.stats.completed')" :value="number_format($stats['completed'])" icon="check-circle" :comparison="__('catalog::vendor_requests.stats.completed_hint')" />
        </div>

        @php
            $columns = [['data' => 'request', 'title' => __('catalog::vendor_requests.columns.request')]];
            if ($kind === \App\Modules\Catalog\Enums\RequestKind::Manufacturing) {
                $columns[] = ['data' => 'quantity', 'title' => __('catalog::vendor_requests.columns.quantity')];
            }
            $columns = [...$columns,
                ['data' => 'status', 'title' => __('catalog::vendor_requests.columns.status')],
                ['data' => 'unread', 'title' => __('catalog::vendor_requests.columns.unread')],
                ['data' => 'updated', 'title' => __('catalog::vendor_requests.columns.updated')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ];
        @endphp
        <x-ui::datatable
            id="vendor-requests-{{ $key }}"
            :url="vendor_route('requests.'.$key.'.data')"
            :columns="$columns"
            :order="[[count($columns) - 2, 'desc']]"
            :empty-title="__('catalog::vendor_requests.list.empty_title')"
            :empty-description="__('catalog::vendor_requests.list.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-requests-{{ $key }}" :search-placeholder="__('catalog::vendor_requests.list.search')">
                    <x-ui::select name="status" :label="__('catalog::vendor_requests.filters.status')" label-hidden :options="$statuses" :placeholder="__('catalog::vendor_requests.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::vendor_requests.filters.status')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    @if ($canManage && $kind !== \App\Modules\Catalog\Enums\RequestKind::Manufacturing)
        <x-ui::modal id="vendor-request-modal" :title="__('catalog::vendor_requests.new.'.$key)" size="lg">
            @include('catalog::admin.partials.remote-states', ['error' => __('catalog::requests.thread.load_error')])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor_requests.create.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-request-form" icon="plus" data-remote-submit>{{ __('catalog::vendor_requests.create.submit') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
