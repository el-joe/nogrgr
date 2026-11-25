{{-- Admin › Website › Blog posts (ADM-081, Figma Admin Panel 76:71416): filters, publish / archive / delete. --}}
@php $canManage = (bool) auth('admin')->user()?->can('content.posts.manage'); @endphp
<x-layouts.panel surface="admin" :title="__('content::admin.posts.title')">
    <x-ui::page-header :title="__('content::admin.posts.title')" :subtitle="__('content::admin.posts.subtitle')"
        :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.posts.title') => null]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.content.posts.create')">{{ __('content::admin.posts.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="posts"
        :url="route('admin.content.posts.data')"
        :columns="[
            ['data' => 'title', 'title' => __('content::admin.posts.columns.post'), 'searchable' => false],
            ['data' => 'category', 'title' => __('content::admin.posts.columns.category'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('content::admin.posts.columns.status'), 'searchable' => false],
            ['data' => 'published_at', 'title' => __('content::admin.posts.columns.published_at'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'desc']]"
        :empty-title="__('content::admin.posts.empty_title')"
        :empty-description="__('content::admin.posts.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="posts" :search-placeholder="__('content::admin.common.search')">
                <x-ui::select name="status" :label="__('content::admin.posts.fields.status')" label-hidden :options="$statuses" :placeholder="__('content::admin.posts.fields.status')" :search="false" allow-clear data-filter :data-filter-label="__('content::admin.posts.fields.status')" class="w-40" />
                <x-ui::select name="category" :label="__('content::admin.posts.fields.category')" label-hidden :options="$categories" :placeholder="__('content::admin.posts.fields.category')" allow-clear data-filter :data-filter-label="__('content::admin.posts.fields.category')" class="w-48" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.content.posts.create')">{{ __('content::admin.posts.new') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
