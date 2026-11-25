{{-- Admin › Website › Static pages (ADM-082, Figma Admin Panel 76:73162): filters, activate / archive / delete. --}}
@php $canManage = (bool) auth('admin')->user()?->can('content.pages.manage'); @endphp
<x-layouts.panel surface="admin" :title="__('content::admin.pages.title')">
    <x-ui::page-header :title="__('content::admin.pages.title')" :subtitle="__('content::admin.pages.subtitle')"
        :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.pages.title') => null]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.content.pages.create')">{{ __('content::admin.pages.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="pages"
        :url="route('admin.content.pages.data')"
        :columns="[
            ['data' => 'title', 'title' => __('content::admin.pages.columns.page'), 'searchable' => false],
            ['data' => 'target', 'title' => __('content::admin.pages.columns.target'), 'searchable' => false],
            ['data' => 'status', 'title' => __('content::admin.pages.columns.status'), 'searchable' => false],
            ['data' => 'updated_at', 'title' => __('content::admin.pages.columns.updated_at'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'desc']]"
        :empty-title="__('content::admin.pages.empty_title')"
        :empty-description="__('content::admin.pages.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="pages" :search-placeholder="__('content::admin.common.search')">
                <x-ui::select name="status" :label="__('content::admin.pages.fields.status')" label-hidden :options="$statuses" :placeholder="__('content::admin.pages.fields.status')" :search="false" allow-clear data-filter :data-filter-label="__('content::admin.pages.fields.status')" class="w-40" />
                <x-ui::select name="target" :label="__('content::admin.pages.fields.target')" label-hidden :options="$targets" :placeholder="__('content::admin.pages.fields.target')" :search="false" allow-clear data-filter :data-filter-label="__('content::admin.pages.fields.target')" class="w-44" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.content.pages.create')">{{ __('content::admin.pages.new') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
