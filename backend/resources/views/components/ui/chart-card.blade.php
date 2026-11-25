{{--
    Chart card (Figma 29:10505): header (title, subtitle, 7D/30D/12M segmented, actions
    slot), Chart.js canvas loaded lazily, legend. Series: brand first, ink second,
    status tones for breakdowns.
    :ranges="['30d' => ['labels' => [...], 'series' => [['label' => 'Gross sales', 'data' => [...], 'tone' => 'brand']]]]"
    Doughnut: one series with 'tones' per slice; the center shows :total.
    :empty="true" shows the empty state instead of the chart.
--}}
@props([
    'id',
    'title',
    'subtitle' => null,
    'type' => 'line',
    'ranges' => [],
    'range' => null,
    'total' => null,
    'height' => 240,
    'empty' => false,
    'emptyTitle' => null,
    'emptyDescription' => null,
])
@php
    $range ??= array_key_first($ranges);
    $current = $ranges[$range] ?? ['labels' => [], 'series' => []];
    $isDonut = $type === 'doughnut';
    $toneClass = ['brand' => 'bg-primary', 'ink' => 'bg-ink', 'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger', 'info' => 'bg-info', 'muted' => 'bg-line-strong'];
    $donutSeries = $current['series'][0] ?? ['data' => [], 'tones' => []];
    $donutSum = array_sum($donutSeries['data'] ?? []);
@endphp
<section data-chart-card {{ $attributes->class(['flex flex-col gap-4 rounded-card border border-line bg-surface p-5 shadow-card']) }} aria-labelledby="{{ $id }}-title">
    <header class="flex flex-wrap items-start gap-3">
        <div class="min-w-0 flex-1">
            <h2 id="{{ $id }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>
            @if ($subtitle)
                <p class="text-xs text-ink-secondary">{{ $subtitle }}</p>
            @endif
        </div>
        @if (count($ranges) > 1 && ! $empty)
            <div class="inline-flex gap-0.5 rounded-chip bg-subtle p-0.5" role="group" aria-label="{{ __('ui.chart.range') }}">
                @foreach (array_keys($ranges) as $key)
                    <button type="button" data-chart-range="{{ $key }}" aria-pressed="{{ $key === $range ? 'true' : 'false' }}" class="h-7 rounded-[5px] px-2.5 text-xs font-medium text-ink-secondary transition-colors hover:text-ink aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card">{{ __('ui.chart.ranges.'.$key) }}</button>
                @endforeach
            </div>
        @endif
        {{ $actions ?? '' }}
    </header>

    @if ($empty)
        <x-ui::empty-state variant="no-data" :title="$emptyTitle ?? __('ui.chart.empty_title')" :description="$emptyDescription ?? __('ui.chart.empty_description')" compact />
    @else
        <div @class(['flex flex-col gap-6 sm:flex-row sm:items-center' => $isDonut])>
            <div class="relative" @style(['height: '.(int) $height.'px', 'width: '.(int) $height.'px' => $isDonut, 'flex-shrink: 0' => $isDonut]) dir="ltr">
                <canvas data-chart="{{ $id }}" role="img" aria-label="{{ $title }}"></canvas>
                @if ($isDonut && $total !== null)
                    <p class="pointer-events-none absolute inset-0 grid place-items-center text-xl font-semibold text-ink"><bdi>{{ $total }}</bdi></p>
                @endif
            </div>
            <ul @class(['flex flex-wrap gap-x-5 gap-y-2 text-xs text-ink-secondary', 'sm:flex-col sm:gap-3' => $isDonut])>
                @if ($isDonut)
                    @foreach ($current['labels'] as $index => $label)
                        <li class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $toneClass[$donutSeries['tones'][$index] ?? 'muted'] ?? 'bg-primary' }}" aria-hidden="true"></span>
                            <span class="text-[13px]">{{ $label }}</span>
                            <span class="text-[13px] font-medium text-ink" dir="ltr">{{ $donutSum > 0 ? \Illuminate\Support\Number::percentage(($donutSeries['data'][$index] ?? 0) / $donutSum * 100, locale: \App\Shared\Support\Locale::numberLocale()) : '0%' }}</span>
                        </li>
                    @endforeach
                @else
                    @foreach ($current['series'] as $index => $series)
                        <li class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $toneClass[$series['tone'] ?? ($index === 0 ? 'brand' : 'ink')] ?? 'bg-primary' }}" aria-hidden="true"></span>{{ $series['label'] }}
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>
        <script type="application/json" data-chart-config="{{ $id }}">@json(['type' => $type, 'range' => $range, 'ranges' => $ranges])</script>
    @endif
</section>
