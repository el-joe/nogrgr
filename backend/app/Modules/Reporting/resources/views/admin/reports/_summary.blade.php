{{-- KPI cards, highlight tiles, charts, funnel and leaderboard of a report tab. --}}
@php
    $comparison = __('reporting::reports.vs_previous', ['days' => \Illuminate\Support\Number::format($filters->days(), locale: \App\Shared\Support\Locale::numberLocale())]);
@endphp
@if ($summary->kpis)
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ $tab->label() }}">
        @foreach ($summary->kpis as $kpi)
            <x-ui::kpi-card
                :label="$kpi['label']"
                :value="$kpi['value']"
                :icon="$kpi['icon']"
                :delta="$kpi['delta']"
                :comparison="$comparison"
                :spark="$kpi['spark']"
                :highlight="$loop->first"
                :count-up="in_array($kpi['format'], ['number', 'percent'], true) ? $kpi['raw'] : null"
                :count-format="$kpi['format']"
                data-reveal="fade-up"
                :data-reveal-delay="$loop->index * 60"
            />
        @endforeach
    </section>
@endif

@if ($summary->tiles)
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('reporting::reports.tiles_label') }}">
        @foreach ($summary->tiles as $tile)
            <div class="grid gap-1 rounded-control bg-subtle px-4 py-3">
                <p class="text-xs text-ink-secondary">{{ $tile['label'] }}</p>
                <p class="truncate text-xl font-semibold text-ink"><bdi>{{ $tile['value'] }}</bdi></p>
            </div>
        @endforeach
    </section>
@endif

@if ($summary->charts)
    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ($summary->charts as $chart)
            @php
                $donut = $chart['type'] === 'doughnut';
                $sum = collect($chart['series'])->sum(fn ($series) => array_sum($series['data']));
            @endphp
            <x-ui::chart-card
                :id="'chart-'.$chart['id']"
                :class="\Illuminate\Support\Arr::toCssClasses(['xl:col-span-2' => ($chart['wide'] ?? false) && count($summary->charts) > 1, 'xl:col-span-3' => ($chart['wide'] ?? false) && count($summary->charts) === 1])"
                :title="$chart['title']"
                :subtitle="$chart['subtitle']"
                :type="$chart['type']"
                :height="$donut ? 180 : 260"
                :ranges="['period' => ['labels' => $chart['labels'], 'series' => $chart['series']]]"
                :total="$chart['total'] ?? null"
                :empty="$sum == 0"
                data-reveal="fade-up"
            />
        @endforeach
    </div>
@endif

@if ($summary->funnel || $summary->leaders)
    <div class="grid gap-4 lg:grid-cols-2">
        @if ($summary->funnel)
            <x-ui::card :title="__('reporting::reports.funnel_title')" data-reveal="fade-up">
                <ul class="grid gap-4" role="list">
                    @foreach ($summary->funnel as $step)
                        <li class="grid gap-2">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-ink">{{ $step['label'] }}</span>
                                <span class="text-xs text-ink-secondary" dir="ltr">{{ $step['display'] }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-subtle" role="presentation">
                                <span class="block h-full rounded-full bg-primary" @style(['width: '.max(0, min(100, $step['percent'])).'%'])></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui::card>
        @endif
        @if ($summary->leaders)
            <x-ui::card :title="__('reporting::reports.leaderboard')" data-reveal="fade-up">
                <ol class="grid gap-4">
                    @foreach ($summary->leaders as $leader)
                        <li class="flex items-center gap-3">
                            <x-ui::avatar :name="$leader['name']" :size="36" />
                            <div class="grid min-w-0 flex-1 gap-1">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate text-sm font-medium text-ink">{{ $leader['name'] }}</span>
                                    <x-ui::badge :tone="$leader['rank'] === 1 ? 'success' : 'neutral'">#{{ $leader['rank'] }}</x-ui::badge>
                                </div>
                                <p class="text-xs text-ink-secondary"><bdi dir="ltr">{{ $leader['value'] }}</bdi> · {{ $leader['detail'] }}</p>
                                <div class="h-1.5 overflow-hidden rounded-full bg-subtle" role="presentation">
                                    <span class="block h-full rounded-full bg-primary" @style(['width: '.max(0, min(100, $leader['percent'])).'%'])></span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui::card>
        @endif
    </div>
@endif
