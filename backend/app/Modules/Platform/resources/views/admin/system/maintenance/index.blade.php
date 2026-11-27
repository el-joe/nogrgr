{{--
    Admin › System tools › Maintenance windows (ADM-113, Figma Admin Panel 80:92875):
    "Schedule window", the next / running window banner, server-side table (window,
    scope, starts, duration, status, view / edit / end now / delete) with status and
    scope filters. Create / edit / view share one modal filled from the row's JSON.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('system.maintenance.manage');
    $bannerText = null;
    if ($next) {
        $status = $next->status();
        $format = fn ($date) => $date->copy()->locale(app()->getLocale())->translatedFormat('D j M H:i');
        $scopeText = $next->scope === \App\Modules\Platform\Enums\MaintenanceScope::Store ? $next->scope->label().' · '.($next->tenant?->name ?? '#'.$next->tenant_id) : $next->scope->label();
        $replace = ['title' => $next->title, 'scope' => $scopeText, 'start' => $format($next->starts_at), 'end' => $next->ends_at ? $format($next->ends_at) : ''];
        $key = $status === \App\Modules\Platform\Enums\MaintenanceWindowStatus::Active ? 'next_active' : 'next_scheduled';
        $bannerText = __('platform::maintenance.'.$key.($next->ends_at ? '' : '_open'), $replace);
    }
@endphp
<x-layouts.panel surface="admin" :title="__('platform::maintenance.title')" data-page="admin-system-tools">
    <x-ui::page-header
        :title="__('platform::maintenance.title')"
        :subtitle="__('platform::maintenance.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.system_tools') => null, __('platform::maintenance.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="plus" data-maint-new>{{ __('platform::maintenance.schedule') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <div class="grid gap-6 *:min-w-0" data-maintenance>
        @if ($bannerText)
            <div class="flex items-start gap-3 rounded-card border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-ink" role="status" id="maintenance-next">
                <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
                <p>{{ $bannerText }} <span class="text-ink-secondary">{{ __('platform::maintenance.panels_note') }}</span></p>
            </div>
        @endif

        <x-ui::datatable
            id="maintenance-windows"
            :url="route('admin.system.maintenance.data')"
            :columns="[
                ['data' => 'title', 'title' => __('platform::maintenance.columns.window')],
                ['data' => 'scope', 'title' => __('platform::maintenance.columns.scope'), 'orderable' => false, 'searchable' => false],
                ['data' => 'starts_at', 'title' => __('platform::maintenance.columns.starts'), 'searchable' => false],
                ['data' => 'duration', 'title' => __('platform::maintenance.columns.duration'), 'orderable' => false, 'searchable' => false],
                ['data' => 'status', 'title' => __('platform::maintenance.columns.status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[2, 'desc']]"
            :empty-title="__('platform::maintenance.empty_title')"
            :empty-description="__('platform::maintenance.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="maintenance-windows">
                    <x-ui::select name="status" :label="__('platform::maintenance.filters.status')" label-hidden :options="$statuses" :placeholder="__('platform::maintenance.filters.all_statuses')" :search="false" allow-clear data-filter :data-filter-label="__('platform::maintenance.filters.status')" class="w-44" />
                    <x-ui::select name="scope" :label="__('platform::maintenance.filters.scope')" label-hidden :options="$scopes" :placeholder="__('platform::maintenance.filters.all_scopes')" :search="false" allow-clear data-filter :data-filter-label="__('platform::maintenance.filters.scope')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
            @if ($canManage)
                <x-slot:empty>
                    <x-ui::button icon="plus" data-maint-new>{{ __('platform::maintenance.schedule') }}</x-ui::button>
                </x-slot:empty>
            @endif
        </x-ui::datatable>
    </div>

    @include('platform::admin.system.maintenance._modal')
</x-layouts.panel>
