{{-- Admin › Website › Blog categories (ADM-080, Figma Admin Panel 76:70524). --}}
@php $canManage = (bool) auth('admin')->user()?->can('content.post-categories.manage'); @endphp
<x-layouts.panel surface="admin" :title="__('content::admin.categories.title')">
    <x-ui::page-header :title="__('content::admin.categories.title')" :subtitle="__('content::admin.categories.subtitle')"
        :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.categories.title') => null]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" :href="route('admin.content.post-categories.create')">{{ __('content::admin.categories.new') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="post-categories"
        :url="route('admin.content.post-categories.data')"
        :columns="[
            ['data' => 'name', 'title' => __('content::admin.categories.fields.name'), 'searchable' => false],
            ['data' => 'posts_count', 'title' => __('content::admin.categories.fields.posts'), 'align' => 'end', 'searchable' => false],
            ['data' => 'is_active', 'title' => __('content::admin.common.active'), 'searchable' => false],
            ['data' => 'sort_order', 'title' => __('content::admin.common.sort_order'), 'align' => 'end', 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'asc']]"
        :empty-title="__('content::admin.categories.empty_title')"
        :empty-description="__('content::admin.categories.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="post-categories" :search-placeholder="__('content::admin.common.search')" />
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.content.post-categories.create')">{{ __('content::admin.categories.new') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
