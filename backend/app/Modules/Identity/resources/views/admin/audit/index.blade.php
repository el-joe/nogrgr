{{--
    Admin › Audit log (ADM-112, Figma Admin Panel 80:91969): server-side table (when,
    admin, action, subject, IP) with search + admin / store / event / date filters,
    CSV export of the filtered rows and the detail modal with the before/after diff
    (ADM-112a, 80:98485).
--}}
@php
    $canExport = (bool) auth('admin')->user()?->can('audit.export');
@endphp
<x-layouts.panel surface="admin" :title="__('identity::audit.title')" data-page="admin-audit">
    <x-ui::page-header
        :title="__('identity::audit.title')"
        :subtitle="__('identity::audit.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('identity::audit.title') => null]"
    >
        @if ($canExport)
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.system.audit.export')" data-audit-export="audit">{{ __('identity::audit.export') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="audit"
        :url="route('admin.system.audit.data')"
        :columns="[
            ['data' => 'created_at', 'title' => __('identity::audit.columns.when')],
            ['data' => 'actor', 'title' => __('identity::audit.columns.actor'), 'orderable' => false],
            ['data' => 'action', 'title' => __('identity::audit.columns.action'), 'orderable' => false],
            ['data' => 'subject', 'title' => __('identity::audit.columns.subject'), 'orderable' => false],
            ['data' => 'ip_address', 'title' => __('identity::audit.columns.ip'), 'orderable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'desc']]"
        :empty-title="__('identity::audit.empty_title')"
        :empty-description="__('identity::audit.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="audit" :search-placeholder="__('identity::audit.search')">
                <x-ui::select name="actor" :label="__('identity::audit.filters.actor')" label-hidden :options="$adminOptions" :placeholder="__('identity::audit.filters.actor')" allow-clear data-filter :data-filter-label="__('identity::audit.filters.actor')" class="w-48" />
                <x-ui::select name="event" :label="__('identity::audit.filters.event')" label-hidden :options="$eventOptions" :placeholder="__('identity::audit.filters.event')" allow-clear data-filter :data-filter-label="__('identity::audit.filters.event')" class="w-56" />
                <x-slot:more>
                    <div class="grid gap-5">
                        <x-ui::select name="tenant" :label="__('identity::audit.filters.tenant')" :options="$tenantSelected" :selected="array_key_first($tenantSelected)" :placeholder="__('identity::audit.filters.tenant')" :ajax-url="route('admin.system.audit.tenants')" allow-clear data-filter data-filter-for="audit" :data-filter-label="__('identity::audit.filters.tenant')" />
                        <x-ui::daterange name="date" :label="__('identity::audit.filters.period')" filter data-filter-for="audit" />
                    </div>
                </x-slot:more>
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>

    <x-ui::modal id="audit-modal" :title="__('identity::audit.detail')" size="lg">
        @include('identity::admin._remote-modal-states', ['error' => __('identity::audit.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
