{{--
    Admin › Support tickets (ADM-116, Figma Admin Panel 80:95200): tabs Open / Waiting on
    vendor / Resolved / Closed / All with counts, server-side list (ticket · category,
    store, priority, assignee, updated, status, view / resolve) with search and priority,
    category, store and assignee filters.
    $tab, $counts, $priorities, $categories, $assignees.
--}}
@php
    $tabItems = [];
    foreach (['open', 'waiting', 'resolved', 'closed', 'all'] as $key) {
        $tabItems[$key] = ['label' => __('support::tickets.tabs.'.$key), 'href' => route('admin.support.tickets.index', ['tab' => $key])]
            + (in_array($key, ['open', 'waiting'], true) ? ['count' => number_format($counts[$key] ?? 0)] : []);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('support::tickets.title')" data-page="admin-support">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('support::tickets.title')"
            :breadcrumbs="[__('support::tickets.breadcrumb_system') => null, __('support::tickets.title') => null]">
            <x-slot:tabs>
                <x-ui::tabs id="support-tabs" :active="$tab" :label="__('support::tickets.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::datatable
            id="support-tickets"
            :url="route('admin.support.tickets.data')"
            :columns="[
                ['data' => 'ticket', 'title' => __('support::tickets.columns.ticket')],
                ['data' => 'store', 'title' => __('support::tickets.columns.store')],
                ['data' => 'priority', 'title' => __('support::tickets.columns.priority')],
                ['data' => 'assignee', 'title' => __('support::tickets.columns.assignee')],
                ['data' => 'updated', 'title' => __('support::tickets.columns.updated')],
                ['data' => 'status', 'title' => __('support::tickets.columns.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('support::tickets.empty.title')"
            :empty-description="__('support::tickets.empty.description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="support-tickets" :search-placeholder="__('support::tickets.filters.search')">
                    <input type="hidden" name="tab" value="{{ $tab }}" data-filter>
                    <x-ui::select name="priority" :label="__('support::tickets.filters.priority')" label-hidden :options="$priorities" :placeholder="__('support::tickets.filters.priority')" :search="false" allow-clear data-filter :data-filter-label="__('support::tickets.filters.priority')" class="w-36" />
                    <x-ui::select name="assignee" :label="__('support::tickets.filters.assignee')" label-hidden :options="$assignees" :placeholder="__('support::tickets.filters.assignee')" allow-clear data-filter :data-filter-label="__('support::tickets.filters.assignee')" class="w-44" />
                    <x-slot:more>
                        <div class="grid gap-4">
                            <x-ui::select name="category" :label="__('support::tickets.filters.category')" :options="$categories" :placeholder="__('support::tickets.filters.category')" :search="false" allow-clear data-filter :data-filter-label="__('support::tickets.filters.category')" />
                            <x-ui::select name="tenant_id" :label="__('support::tickets.filters.store')" :options="[]" :placeholder="__('support::tickets.filters.store')" :ajax-url="route('admin.support.tickets.stores')" allow-clear data-filter :data-filter-label="__('support::tickets.filters.store')" />
                        </div>
                    </x-slot:more>
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
