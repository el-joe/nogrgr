{{--
    Admin › Storefront › Template parts (ADM-088, Figma Admin Panel 78:79365; old
    TemplatePartsPage): server-side list filtered by theme + part type; default badge, on /
    off switch, set default and delete with confirmation; add / edit in a modal (ADM-088a).
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('storefront.template-parts.manage');
    $createUrl = route('admin.storefront.template-parts.create', array_filter(['theme' => $theme ?: null, 'type' => $type?->value]));
@endphp
<x-layouts.panel surface="admin" :title="__('content::admin_themes.parts.title')" data-page="admin-themes">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('content::admin_themes.parts.title')"
            :subtitle="__('content::admin_themes.parts.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.themes') => route('admin.storefront.themes.index'), __('navigation.admin.template_parts') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="theme-part-modal" :data-url="$createUrl">{{ __('content::admin_themes.parts.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="theme-parts"
            :url="route('admin.storefront.template-parts.data')"
            :columns="[
                ['data' => 'part', 'title' => __('content::admin_themes.parts.columns.part'), 'orderable' => false, 'searchable' => false, 'className' => 'w-full'],
                ['data' => 'theme', 'title' => __('content::admin_themes.parts.columns.theme'), 'orderable' => false, 'searchable' => false],
                ['data' => 'status', 'title' => __('content::admin_themes.parts.columns.status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :empty-title="__('content::admin_themes.parts.empty.title')"
            :empty-description="__('content::admin_themes.parts.empty.description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="theme-parts" :search-placeholder="__('content::admin_themes.parts.search')">
                    <x-ui::select name="theme_id" :label="__('content::admin_themes.parts.fields.theme')" label-hidden :options="['' => __('content::admin_themes.parts.all_themes')] + $themes" :selected="$theme ? (string) $theme : ''" data-filter :data-filter-label="__('content::admin_themes.parts.fields.theme')" class="w-56" />
                    <x-ui::select name="type" :label="__('content::admin_themes.parts.fields.type')" label-hidden :options="['' => __('content::admin_themes.parts.all_types')] + $types" :selected="$type?->value ?? ''" :search="false" data-filter :data-filter-label="__('content::admin_themes.parts.fields.type')" class="w-48" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-remote-modal="theme-part-modal" :data-url="$createUrl">{{ __('content::admin_themes.parts.add') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @if ($canManage)
        <x-ui::modal id="theme-part-modal" size="lg" :title="__('content::admin_themes.parts.create_title')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::admin_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="theme-part-form" icon="check" data-remote-submit>{{ __('content::admin_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
