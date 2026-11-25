{{--
    Admin › Vendors › Subscriptions (ADM-047, Figma 73:58869): status tabs with counts,
    KPI cards (MRR, new, churn, trial conversion), server-side table filtered by plan /
    status / gateway, invoice link, renew / cancel, CSV export and "Assign plan".
--}}
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('subscriptions.manage');
    $canExport = (bool) $admin?->can('subscriptions.export');
    $status = request()->query('status');
    $status = is_string($status) && array_key_exists($status, $statuses) ? $status : 'all';
    $tabs = ['all' => ['label' => __('billing::subscriptions.tabs.all'), 'href' => route('admin.billing.subscriptions.index')]];
    foreach (['active', 'trial', 'past_due', 'cancelled', 'expired'] as $key) {
        $tabs[$key] = ['label' => $statuses[$key], 'count' => $counts[$key] ?? 0, 'href' => route('admin.billing.subscriptions.index', ['status' => $key])];
    }
    $percent = fn (?float $value) => $value === null ? '—' : \Illuminate\Support\Number::format($value, maxPrecision: 1, locale: \App\Shared\Support\Locale::numberLocale()).'%';
@endphp
<x-layouts.panel surface="admin" :title="__('billing::subscriptions.title')" data-page="billing">
    <x-ui::page-header
        :title="__('billing::subscriptions.title')"
        :breadcrumbs="[__('navigation.sections.vendors') => null, __('billing::subscriptions.title') => null]"
    >
        <x-slot:actions>
            @if ($canExport)
                <x-ui::button variant="secondary" icon="download" :href="route('admin.billing.subscriptions.export')" data-export-for="subscriptions">{{ __('billing::subscriptions.export') }}</x-ui::button>
            @endif
            @if ($canManage)
                <x-ui::button icon="plus" data-overlay-open="assign-plan">{{ __('billing::subscriptions.assign') }}</x-ui::button>
            @endif
        </x-slot:actions>
        <x-slot:tabs>
            <x-ui::tabs id="subscription-tabs" :active="$status" :label="__('billing::subscriptions.title')" :items="$tabs" />
        </x-slot:tabs>
    </x-ui::page-header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('billing::subscriptions.kpis') }}">
        <x-ui::kpi-card highlight icon="credit-card" :label="__('billing::subscriptions.kpi.mrr')" :value="$summary['mrr']->format()" :comparison="__('billing::subscriptions.kpi.mrr_hint')" />
        <x-ui::kpi-card icon="plus" :label="__('billing::subscriptions.kpi.new')" :value="\Illuminate\Support\Number::format($summary['new'], locale: \App\Shared\Support\Locale::numberLocale())" :comparison="__('billing::subscriptions.kpi.last_30_days')" />
        <x-ui::kpi-card icon="return" :label="__('billing::subscriptions.kpi.churn')" :value="$percent($summary['churn_percent'])" :comparison="__('billing::subscriptions.kpi.last_30_days')" />
        <x-ui::kpi-card icon="percent" :label="__('billing::subscriptions.kpi.trials')" :value="$percent($summary['trial_conversion_percent'])" :comparison="__('billing::subscriptions.kpi.trials_hint')" />
    </section>

    <x-ui::datatable
        id="subscriptions"
        :url="route('admin.billing.subscriptions.data')"
        :columns="[
            ['data' => 'store', 'title' => __('billing::subscriptions.columns.store'), 'orderable' => false, 'searchable' => false],
            ['data' => 'plan_id', 'title' => __('billing::subscriptions.columns.plan'), 'searchable' => false],
            ['data' => 'kind', 'title' => __('billing::subscriptions.columns.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'amount_minor', 'title' => __('billing::subscriptions.columns.amount'), 'align' => 'end', 'searchable' => false],
            ['data' => 'current_period_ends_at', 'title' => __('billing::subscriptions.columns.next_billing'), 'searchable' => false],
            ['data' => 'status', 'title' => __('billing::subscriptions.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[4, 'asc']]"
        :empty-title="__('billing::subscriptions.empty_title')"
        :empty-description="__('billing::subscriptions.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="subscriptions" :search-placeholder="__('billing::subscriptions.search')">
                <x-ui::select name="plan_id" :label="__('billing::subscriptions.columns.plan')" label-hidden :options="$plans" :placeholder="__('billing::subscriptions.columns.plan')" allow-clear data-filter :data-filter-label="__('billing::subscriptions.columns.plan')" class="w-40" />
                <x-ui::select name="status" :label="__('billing::subscriptions.columns.status')" label-hidden :options="$statuses" :placeholder="__('billing::subscriptions.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::subscriptions.columns.status')" class="w-40" />
                <x-ui::select name="gateway" :label="__('billing::subscriptions.columns.gateway')" label-hidden :options="$gateways" :placeholder="__('billing::subscriptions.columns.gateway')" :search="false" allow-clear data-filter :data-filter-label="__('billing::subscriptions.columns.gateway')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" data-overlay-open="assign-plan">{{ __('billing::subscriptions.assign') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>

    @if ($canManage)
        <x-ui::modal id="assign-plan" :title="__('billing::subscriptions.assign')" :subtitle="__('billing::subscriptions.assign_hint')" close-on-success>
            @include('billing::admin.subscriptions._assign-form', ['activePlans' => $activePlans, 'terms' => $terms, 'tenantId' => null])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="assign-plan-form" icon="check">{{ __('billing::subscriptions.assign_submit') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
