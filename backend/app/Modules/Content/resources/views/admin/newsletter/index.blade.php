{{-- Admin › Website › Newsletter subscribers (ADM-084, Figma Admin Panel 76:75699): counts, filter, export, delete. --}}
@php $canExport = (bool) auth('admin')->user()?->can('content.newsletter.export'); @endphp
<x-layouts.panel surface="admin" :title="__('content::admin.newsletter.title')">
    <x-ui::page-header :title="__('content::admin.newsletter.title')" :subtitle="__('content::admin.newsletter.subtitle')"
        :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.newsletter.title') => null]">
        @if ($canExport)
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.content.newsletter.export')" data-export-link>{{ __('content::admin.newsletter.export') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach (['subscribed' => 'users', 'pending' => 'clock', 'unsubscribed' => 'x-circle'] as $key => $icon)
            <x-ui::kpi-card :label="__('content::admin.newsletter.counts.'.$key)" :value="\Illuminate\Support\Number::format($counts[$key] ?? 0, locale: \App\Shared\Support\Locale::numberLocale())" :icon="$icon" />
        @endforeach
    </section>

    <x-ui::datatable
        id="newsletter"
        :url="route('admin.content.newsletter.data')"
        :columns="[
            ['data' => 'email', 'title' => __('content::admin.newsletter.columns.email'), 'searchable' => false],
            ['data' => 'status', 'title' => __('content::admin.newsletter.columns.status'), 'searchable' => false],
            ['data' => 'locale', 'title' => __('content::admin.newsletter.columns.locale'), 'searchable' => false],
            ['data' => 'created_at', 'title' => __('content::admin.newsletter.columns.subscribed_at'), 'searchable' => false],
            ['data' => 'confirmed_at', 'title' => __('content::admin.newsletter.columns.confirmed_at'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'desc']]"
        :empty-title="__('content::admin.newsletter.empty_title')"
        :empty-description="__('content::admin.newsletter.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="newsletter" :search-placeholder="__('content::admin.common.search')">
                <x-ui::select name="status" :label="__('content::admin.newsletter.columns.status')" label-hidden :options="$statuses" :placeholder="__('content::admin.newsletter.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('content::admin.newsletter.columns.status')" class="w-48" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>
</x-layouts.panel>
