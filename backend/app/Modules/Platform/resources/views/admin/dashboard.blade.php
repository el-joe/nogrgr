{{--
    Admin dashboard (Figma 41:12632; mobile 43:13511, RTL 56:17016, dark 57:17198).
    KPIs, revenue, orders by status, recent orders and top vendors read the central
    Reporting facts (USD); the country and period filters reload the page; "Export
    report" downloads the Sales report of the same window. Platform health is live.
--}}
@php
    /** @var array<string, mixed> $overview */
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
    $firstName = \Illuminate\Support\Str::of($admin?->name ?? '')->before(' ')->value();
    $number = fn ($value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
    $period = $overview['from']->locale(app()->getLocale())->isoFormat('D MMM').' – '.$overview['to']->locale(app()->getLocale())->isoFormat('D MMM YYYY');
    $filters = $overview['filters'];
    $reportQuery = array_filter(['from' => $overview['from']->toDateString(), 'to' => $overview['to']->toDateString(), 'country_id' => $filters->countryId]);
    $canReports = (bool) $admin?->can('reports.view');
    $canExport = (bool) $admin?->can('reports.export');
    $ordersUrl = $admin?->can('orders.view') && \Illuminate\Support\Facades\Route::has('admin.orders.index') ? route('admin.orders.index') : null;
    $toolsUrl = collect(['admin.system.maintenance.index' => 'system.maintenance.view', 'admin.system.cache.index' => 'system.cache.view', 'admin.system.docs.index' => 'system.docs.view'])
        ->filter(fn ($permission, $route) => \Illuminate\Support\Facades\Route::has($route) && $admin?->can($permission))->keys()->map(fn ($route) => route($route))->first();
    $donut = $overview['ordersByStatus'];
@endphp
<x-layouts.panel surface="admin" :title="__('platform::panel.dashboard')">
    <x-ui::page-header
        :title="__('platform::dashboard.greeting.'.$greeting, ['name' => $firstName])"
        :subtitle="__('platform::dashboard.subtitle', ['count' => $number($overview['activeStores']), 'date' => now()->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY')])"
        data-reveal="fade-up"
    >
        <x-slot:actions>
            <x-ui::filters-bar :search="false" reload :chips="false" class="w-full sm:w-auto">
                <x-ui::select
                    name="country_id"
                    :label="__('platform::dashboard.country')"
                    label-hidden
                    :options="$countries"
                    :selected="$filters->countryId"
                    :placeholder="__('platform::dashboard.all_countries')"
                    allow-clear
                    data-filter
                    :data-filter-label="__('platform::dashboard.country')"
                    class="w-full sm:w-44"
                />
                <x-ui::daterange
                    name="period"
                    from-name="period_from"
                    to-name="period_to"
                    :label="__('platform::dashboard.period')"
                    label-hidden
                    :from="$overview['from']->toDateString()"
                    :to="$overview['to']->toDateString()"
                    :max="now()->toDateString()"
                    filter
                    compact
                    class="w-full sm:w-[260px]"
                />
                @if ($canExport)
                    <x-slot:end>
                        <x-ui::button icon="download" :href="route('admin.reports.export', ['tab' => 'sales', ...$reportQuery])">{{ __('platform::dashboard.export_report') }}</x-ui::button>
                    </x-slot:end>
                @endif
            </x-ui::filters-bar>
        </x-slot:actions>
    </x-ui::page-header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('platform::dashboard.kpis') }}">
        @foreach ($overview['kpis'] as $kpi)
            <x-ui::kpi-card
                :label="__('platform::dashboard.kpi.'.$kpi['key'])"
                :value="$kpi['value']"
                :icon="$kpi['icon']"
                :delta="$kpi['delta']"
                :spark="$kpi['spark']"
                :comparison="__('platform::dashboard.vs_previous', ['days' => $number($overview['days'])])"
                :highlight="$loop->first"
                :count-up="$kpi['format'] === 'money' ? null : $kpi['raw']"
                :count-format="$kpi['format']"
                data-reveal="fade-up"
                :data-reveal-delay="$loop->index * 60"
            >
                <x-slot:menu>
                    <x-ui::dropdown align="end" :width="200" :label="__('platform::dashboard.kpi_menu', ['metric' => __('platform::dashboard.kpi.'.$kpi['key'])])">
                        <x-slot:trigger>
                            <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" @class(['grid size-8 place-items-center rounded-chip', 'text-white/80 hover:bg-white/15 hover:text-white' => $loop->first, 'text-ink-muted hover:bg-subtle hover:text-ink' => ! $loop->first]) aria-label="{{ __('platform::dashboard.kpi_menu', ['metric' => __('platform::dashboard.kpi.'.$kpi['key'])]) }}" title="{{ __('platform::dashboard.kpi_menu', ['metric' => __('platform::dashboard.kpi.'.$kpi['key'])]) }}">
                                <x-ui::icon name="more" :size="18" />
                            </button>
                        </x-slot:trigger>
                        <x-ui::dropdown-item icon="copy" :data-copy-text="__('platform::dashboard.kpi.'.$kpi['key']).': '.$kpi['value']">{{ __('platform::dashboard.copy_value') }}</x-ui::dropdown-item>
                        <x-ui::dropdown-item icon="calendar" data-click-target="#period">{{ __('platform::dashboard.change_period') }}</x-ui::dropdown-item>
                        @if ($canReports)
                            <x-ui::dropdown-item icon="chart" :href="route('admin.reports.show', ['tab' => $kpi['key'] === 'refund_rate' ? 'refunds' : ($kpi['key'] === 'platform_profit' ? 'profit' : 'sales'), ...$reportQuery])">{{ __('platform::dashboard.open_report') }}</x-ui::dropdown-item>
                        @endif
                    </x-ui::dropdown>
                </x-slot:menu>
            </x-ui::kpi-card>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        <x-ui::chart-card
            id="revenue"
            class="xl:col-span-2"
            :title="__('platform::dashboard.revenue.title')"
            :subtitle="__('platform::dashboard.revenue.subtitle')"
            type="line"
            :height="260"
            :ranges="collect($overview['revenue'])->map(fn ($range) => ['labels' => $range['labels'], 'series' => [
                ['label' => __('platform::dashboard.revenue.gross'), 'data' => $range['gross'], 'tone' => 'brand'],
                ['label' => __('platform::dashboard.revenue.platform'), 'data' => $range['platform'], 'tone' => 'ink'],
            ]])->all()"
            range="30d"
            data-reveal="fade-up"
        />
        <x-ui::chart-card
            id="orders-by-status"
            :title="__('platform::dashboard.orders_by_status.title')"
            :subtitle="$period"
            type="doughnut"
            :height="180"
            :ranges="['period' => ['labels' => $donut['labels'], 'series' => [['label' => __('platform::dashboard.orders_by_status.title'), 'data' => $donut['data'], 'tones' => $donut['tones']]]]]"
            :total="$number($donut['total'])"
            :empty="$donut['total'] === 0"
            :empty-title="__('platform::dashboard.orders_by_status.empty_title')"
            :empty-description="__('platform::dashboard.orders_by_status.empty_description')"
            data-reveal="fade-up"
        />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <x-ui::card id="recent-orders" class="xl:col-span-2" :title="__('platform::dashboard.recent_orders.title')" data-reveal="fade-up">
            @if ($ordersUrl)
                <x-slot:actions>
                    <a href="{{ $ordersUrl }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('platform::dashboard.view_all') }}</a>
                </x-slot:actions>
            @endif
            <div class="overflow-x-auto rounded-control border border-line">
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-[13px] text-ink-secondary">
                        <tr>
                            <th scope="col" class="h-11 px-4 text-start font-medium">{{ __('platform::dashboard.recent_orders.order') }}</th>
                            <th scope="col" class="h-11 px-4 text-start font-medium">{{ __('platform::dashboard.recent_orders.vendor') }}</th>
                            <th scope="col" class="hidden h-11 px-4 text-start font-medium md:table-cell">{{ __('platform::dashboard.recent_orders.country') }}</th>
                            <th scope="col" class="h-11 px-4 text-end font-medium">{{ __('platform::dashboard.recent_orders.total') }}</th>
                            <th scope="col" class="h-11 px-4 text-start font-medium">{{ __('platform::dashboard.recent_orders.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($overview['recentOrders'] as $order)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3">
                                    <span class="block font-medium text-ink" dir="ltr">#{{ $order['number'] }}</span>
                                    <span class="text-xs text-ink-secondary">{{ $order['ago'] }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="block font-medium text-ink">{{ $order['store'] }}</span>
                                    <span class="text-xs text-ink-secondary" dir="ltr">{{ $order['slug'] }}</span>
                                </td>
                                <td class="hidden px-4 py-3 text-ink md:table-cell">{{ $order['country'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-medium text-ink" dir="ltr">{{ $order['total'] }}</td>
                                <td class="px-4 py-3"><x-ui::status :status="$order['status']">{{ $order['status_label'] }}</x-ui::status></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-ui::empty-state variant="no-data" icon="cart" compact :title="__('platform::dashboard.recent_orders.empty_title')" :description="__('platform::dashboard.recent_orders.empty_description')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui::card>

        <x-ui::card id="needs-attention" :title="__('platform::dashboard.attention.title')" data-reveal="fade-up">
            <ul class="-mx-2 grid gap-1" role="list">
                @foreach ($overview['attention'] as $row)
                    @php
                        $toneClass = ['danger' => 'bg-danger', 'warning' => 'bg-warning', 'info' => 'bg-info'][$row['tone']] ?? 'bg-primary';
                    @endphp
                    <li>
                        @if ($row['url'])<a href="{{ $row['url'] }}" class="flex min-h-14 items-center gap-3 rounded-control px-2 hover:bg-subtle">@else<div class="flex min-h-14 items-center gap-3 px-2">@endif
                            <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$row['icon']" :size="18" /></span>
                            <span class="min-w-0 flex-1 text-sm text-ink">{{ __('platform::dashboard.attention.'.$row['key']) }}</span>
                            <span @class(['grid h-5 min-w-5 place-items-center rounded-full px-1.5 text-xs font-semibold', $toneClass.' text-white' => $row['count'] > 0, 'bg-subtle text-ink-secondary' => $row['count'] === 0]) dir="ltr">{{ $number($row['count']) }}</span>
                            @if ($row['url'])<x-ui::icon name="chevron-right" :size="16" class="text-ink-muted rtl:rotate-180" />@endif
                        @if ($row['url'])</a>@else</div>@endif
                    </li>
                @endforeach
            </ul>
        </x-ui::card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui::card id="top-vendors" :title="__('platform::dashboard.top_vendors.title')" data-reveal="fade-up">
            @if ($canReports)
                <x-slot:actions>
                    <a href="{{ route('admin.reports.show', ['tab' => 'vendors', ...$reportQuery]) }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('platform::dashboard.leaderboard') }}</a>
                </x-slot:actions>
            @endif
            @if ($overview['topVendors'])
                <ol class="grid gap-4">
                    @foreach ($overview['topVendors'] as $vendor)
                        <li class="flex items-center gap-3">
                            <span class="w-4 text-sm text-ink-secondary" dir="ltr">{{ $loop->iteration }}</span>
                            <x-ui::avatar :name="$vendor['name']" :size="32" :tone="$loop->first ? 'brand' : 'inverse'" />
                            <div class="grid min-w-0 flex-1 gap-1.5">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm font-medium text-ink">{{ $vendor['name'] }}</span>
                                    <span class="whitespace-nowrap text-sm font-medium text-ink" dir="ltr">{{ $vendor['gross'] }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-subtle" role="presentation">
                                    <span class="block h-full rounded-full bg-primary" @style(['width: '.max(2, min(100, $vendor['percent'])).'%'])></span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui::empty-state variant="no-data" icon="store" compact :title="__('platform::dashboard.top_vendors.empty_title')" :description="__('platform::dashboard.top_vendors.empty_description')" />
            @endif
        </x-ui::card>

        <x-ui::card id="platform-health" :title="__('platform::dashboard.health.title')" data-reveal="fade-up">
            @if ($toolsUrl)
                <x-slot:actions>
                    <a href="{{ $toolsUrl }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('platform::dashboard.system_tools') }}</a>
                </x-slot:actions>
            @endif
            <ul class="grid gap-5" role="list">
                @foreach ($overview['health'] as $service)
                    <li class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">{{ __('platform::dashboard.health.'.$service['key']) }}</p>
                            <p class="text-xs text-ink-secondary">{{ __('platform::dashboard.health.'.$service['key'].'_detail', ['driver' => $service['detail']]) }}</p>
                        </div>
                        <x-ui::status :status="$service['status']" variant="outline" />
                    </li>
                @endforeach
            </ul>
        </x-ui::card>
    </div>
</x-layouts.panel>
