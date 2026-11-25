{{--
    Admin › Vendors › Pending registrations (ADM-042, Figma 73:51922): sign-ups that have
    not finished payment or store setup. Filters status + plan, search e-mail / store;
    columns Applicant, Store, Plan, Step, Started, Status, actions (view, resend link,
    delete). A registration has nothing to edit, so the Figma pencil is not rendered.
--}}
@php
    $number = fn (int $value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-layouts.panel surface="admin" :title="__('platform::registrations.title')" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="registrations">
        <x-ui::page-header
            :title="__('platform::registrations.title')"
            :subtitle="trans_choice('platform::registrations.subtitle', $counts['all'] ?? 0, ['count' => $number($counts['all'] ?? 0)])"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::registrations.title') => null]"
        />

        <x-ui::datatable
            id="pending-registrations"
            :url="route('admin.vendors.pending.data')"
            :columns="[
                ['data' => 'applicant', 'title' => __('platform::registrations.columns.applicant'), 'searchable' => false],
                ['data' => 'store', 'title' => __('platform::registrations.columns.store'), 'searchable' => false],
                ['data' => 'plan', 'title' => __('platform::registrations.columns.plan'), 'searchable' => false],
                ['data' => 'step', 'title' => __('platform::registrations.columns.step'), 'searchable' => false],
                ['data' => 'started', 'title' => __('platform::registrations.columns.started'), 'searchable' => false],
                ['data' => 'status', 'title' => __('platform::registrations.columns.status'), 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[4, 'desc']]"
            :empty-title="__('platform::registrations.empty_title')"
            :empty-description="__('platform::registrations.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="pending-registrations" :search-placeholder="__('platform::registrations.search')">
                    <x-ui::select name="status" :label="__('platform::registrations.filters.status')" label-hidden :options="$statuses" :placeholder="__('platform::registrations.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::registrations.filters.status')" class="w-44" />
                    <x-ui::select name="plan_id" :label="__('platform::registrations.filters.plan')" label-hidden :options="$plans" :placeholder="__('platform::registrations.filters.plan')" allow-clear data-filter :data-filter-label="__('platform::registrations.filters.plan')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>
</x-layouts.panel>
