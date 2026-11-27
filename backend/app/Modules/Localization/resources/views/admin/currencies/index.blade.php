{{--
    Admin › Localization › Currencies (ADM-095, Figma 79:84722): base USD, rates from
    open.er-api.com (daily job + "Sync rates now"), last-sync banner, Active / All tabs,
    server-side table with search + status filter, active switch, edit, delete.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('localization.currencies.manage');
    $tableUrl = route('admin.localization.currencies.data', $tab === 'all' ? [] : ['status' => 'active']);
    $format = fn (\Illuminate\Support\Carbon $date) => $date->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm');
@endphp
<x-layouts.panel surface="admin" :title="__('localization::admin.currencies.title')">
    <x-ui::page-header
        :title="__('localization::admin.currencies.title')"
        :subtitle="__('localization::admin.currencies.subtitle', ['time' => \App\Modules\Localization\Services\CurrencyService::SYNC_AT])"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, __('localization::admin.currencies.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button
                    variant="secondary"
                    icon="refresh"
                    :data-confirm="__('localization::admin.currencies.sync_message')"
                    :data-confirm-title="__('localization::admin.currencies.sync_title')"
                    :data-confirm-label="__('localization::admin.currencies.sync')"
                    data-confirm-tone="brand"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.localization.currencies.sync')"
                >{{ __('localization::admin.currencies.sync') }}</x-ui::button>
                <x-ui::button icon="plus" :href="route('admin.localization.currencies.create')">{{ __('localization::admin.currencies.add') }}</x-ui::button>
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            <x-ui::tabs id="currencies-tabs" :active="$tab" :label="__('localization::admin.currencies.title')" :items="[
                'active' => ['label' => __('localization::admin.tabs.active'), 'count' => $counts['active'], 'href' => route('admin.localization.currencies.index')],
                'all' => ['label' => __('localization::admin.tabs.all'), 'count' => $counts['all'], 'href' => route('admin.localization.currencies.index', ['tab' => 'all'])],
            ]" />
        </x-slot:tabs>
    </x-ui::page-header>

    <div @class(['flex items-center gap-2.5 rounded-control px-4 py-3 text-[13px]', 'bg-success-bg text-ink' => $lastSyncedAt, 'bg-warning-bg text-ink' => ! $lastSyncedAt]) role="status">
        <x-ui::icon :name="$lastSyncedAt ? 'check-circle' : 'clock'" :size="18" @class(['text-success' => $lastSyncedAt, 'text-warning' => ! $lastSyncedAt]) />
        @if ($lastSyncedAt)
            {{ __('localization::admin.currencies.last_sync', ['date' => $format($lastSyncedAt), 'next' => $format($nextSyncAt)]) }}
        @else
            {{ __('localization::admin.currencies.never_synced_banner', ['next' => $format($nextSyncAt)]) }}
        @endif
    </div>

    @if ($unsynced !== [])
        <div class="flex items-start gap-2.5 rounded-control bg-warning-bg px-4 py-3 text-[13px] text-ink" role="alert" data-unsynced-currencies>
            <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
            <span>{{ trans_choice('localization::admin.currencies.unsynced_warning', count($unsynced), ['codes' => implode(', ', $unsynced), 'count' => count($unsynced)]) }}</span>
        </div>
    @endif

    <x-ui::datatable
        id="currencies"
        :url="$tableUrl"
        :columns="[
            ['data' => 'name', 'title' => __('localization::admin.currencies.columns.currency')],
            ['data' => 'symbol', 'title' => __('localization::admin.currencies.columns.symbol')],
            ['data' => 'rate', 'title' => __('localization::admin.currencies.columns.rate'), 'align' => 'end'],
            ['data' => 'decimals', 'title' => __('localization::admin.currencies.columns.decimals'), 'align' => 'end'],
            ['data' => 'countries_count', 'title' => __('localization::admin.currencies.columns.countries'), 'searchable' => false],
            ['data' => 'rate_synced_at', 'title' => __('localization::admin.currencies.columns.synced')],
            ['data' => 'is_active', 'title' => __('localization::admin.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('localization::admin.currencies.empty_title')"
        :empty-description="__('localization::admin.currencies.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="currencies" :search-placeholder="__('localization::admin.currencies.search')">
                @if ($tab === 'all')
                    <x-ui::select name="status" :label="__('localization::admin.columns.status')" label-hidden :options="\App\Modules\Localization\Enums\ActiveState::options()" :placeholder="__('localization::admin.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('localization::admin.columns.status')" class="w-40" />
                @endif
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.localization.currencies.create')">{{ __('localization::admin.currencies.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>
</x-layouts.panel>
