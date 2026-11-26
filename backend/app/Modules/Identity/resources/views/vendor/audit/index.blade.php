{{--
    Vendor › Settings › Audit log (VEN-086, Figma Vendor Panel 91:118575): server-side table
    (when, member, action, masked IP) of the store team's actions — staff, Nogrgr support
    and impersonation sessions — with search, Member / Area filters and a date range under
    "More filters". Times are in the store timezone.
--}}
<x-vendor::layout :title="__('identity::vendor_audit.title')" page="vendor-audit">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('identity::vendor_audit.title')" :subtitle="__('identity::vendor_audit.subtitle', ['timezone' => str_replace('_', ' ', $timezone)])" :breadcrumbs="[__('navigation.admin.settings') => null, __('identity::vendor_audit.title') => null]" />

        <x-ui::datatable
            id="vendor-audit"
            :url="vendor_route('settings.audit.data')"
            :columns="[
                ['data' => 'created_at', 'title' => __('identity::vendor_audit.columns.when')],
                ['data' => 'member', 'title' => __('identity::vendor_audit.columns.member'), 'orderable' => false],
                ['data' => 'action', 'title' => __('identity::vendor_audit.columns.action'), 'orderable' => false],
                ['data' => 'ip_address', 'title' => __('identity::vendor_audit.columns.ip'), 'orderable' => false],
            ]"
            :order="[[0, 'desc']]"
            :empty-title="__('identity::vendor_audit.empty_title')"
            :empty-description="__('identity::vendor_audit.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-audit" :search-placeholder="__('identity::vendor_audit.search')">
                    <x-ui::select name="actor" :label="__('identity::vendor_audit.filters.member')" label-hidden :options="$members" :placeholder="__('identity::vendor_audit.filters.member')" allow-clear data-filter :data-filter-label="__('identity::vendor_audit.filters.member')" class="w-44" />
                    <x-ui::select name="area" :label="__('identity::vendor_audit.filters.area')" label-hidden :options="$areas" :placeholder="__('identity::vendor_audit.filters.area')" allow-clear data-filter :data-filter-label="__('identity::vendor_audit.filters.area')" class="w-40" />
                    <x-slot:more>
                        <x-ui::daterange name="date" :label="__('identity::vendor_audit.filters.period')" filter data-filter-for="vendor-audit" />
                    </x-slot:more>
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-vendor::layout>
