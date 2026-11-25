{{--
    Admin › Vendors › Compliance (ADM-044, Figma 73:53809): status tabs with counts
    (Needs review default, Needs action, Verified, Rejected), filters document type +
    country, search store; columns Store, Documents, Submitted, Risk, Status, actions.
--}}
@php
    $tabs = [];
    foreach (\App\Modules\Platform\Enums\ComplianceStatus::cases() as $case) {
        $tabs[$case->value] = ['label' => $case->label(), 'count' => $counts[$case->value] ?? 0, 'href' => route('admin.vendors.compliance.index', ['status' => $case->value])];
    }
@endphp
<x-layouts.panel surface="admin" :title="__('platform::compliance.title')" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="compliance">
        <x-ui::page-header
            :title="__('platform::compliance.title')"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::compliance.title') => null]"
        >
            <x-slot:tabs>
                <x-ui::tabs id="compliance-tabs" :active="$status->value" :label="__('platform::compliance.title')" :items="$tabs" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="compliance"
            :url="route('admin.vendors.compliance.data', ['status' => $status->value])"
            :columns="[
                ['data' => 'store', 'title' => __('platform::compliance.columns.store'), 'searchable' => false],
                ['data' => 'documents', 'title' => __('platform::compliance.columns.documents'), 'orderable' => false, 'searchable' => false],
                ['data' => 'submitted', 'title' => __('platform::compliance.columns.submitted'), 'searchable' => false],
                ['data' => 'risk', 'title' => __('platform::compliance.columns.risk'), 'searchable' => false],
                ['data' => 'status', 'title' => __('platform::compliance.columns.status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[2, 'desc']]"
            :empty-title="__('platform::compliance.empty.'.$status->value.'.title')"
            :empty-description="__('platform::compliance.empty.'.$status->value.'.description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="compliance" :search-placeholder="__('platform::compliance.search')">
                    <x-ui::select name="document" :label="__('platform::compliance.filters.document')" label-hidden :options="$documentTypes" :placeholder="__('platform::compliance.filters.document')" :search="false" allow-clear data-filter :data-filter-label="__('platform::compliance.filters.document')" class="w-48" />
                    <x-ui::select name="country_id" :label="__('platform::compliance.filters.country')" label-hidden :options="$countries" :placeholder="__('platform::compliance.filters.country')" allow-clear data-filter :data-filter-label="__('platform::compliance.filters.country')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
