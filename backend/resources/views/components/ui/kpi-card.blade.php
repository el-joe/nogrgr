{{--
    KPI card (Figma 29:10395): padding 20, radius 16, Shadow/XS. 40px icon chip, 28/36
    value, trend pill (up = success, down = danger, neutral), comparison text, sparkline.
    highlight = brand → ink gradient (once per dashboard).
    count-up="18402" count-format="number|percent|currency:USD" animates the value once.
--}}
@props([
    'label',
    'value',
    'icon' => 'chart',
    'delta' => null,
    'trend' => null,
    'comparison' => null,
    'spark' => [],
    'highlight' => false,
    'countUp' => null,
    'countFormat' => 'number',
])
@php
    $trend ??= $delta === null ? 'neutral' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral'));
    $deltaText = $delta === null ? null : ($delta > 0 ? '+' : ($delta < 0 ? '−' : '')).\Illuminate\Support\Number::format(abs((float) $delta), maxPrecision: 1, locale: \App\Shared\Support\Locale::numberLocale()).'%';
    $trendIcon = ['up' => 'arrow-up', 'down' => 'arrow-down', 'neutral' => 'minus'][$trend];
    $points = \App\Shared\Support\Ui::sparkline(array_values($spark));
@endphp
<article {{ $attributes->class([
    'relative flex flex-col gap-4 rounded-card p-5',
    'bg-linear-to-br from-[var(--nog-highlight-from)] to-[var(--nog-highlight-to)] text-white shadow-raised' => $highlight,
    'border border-line bg-surface text-ink shadow-card' => ! $highlight,
]) }}>
    <header class="flex items-center gap-3">
        <span @class(['grid size-10 shrink-0 place-items-center rounded-control', 'bg-white/15 text-white' => $highlight, 'bg-primary-subtle text-ink-brand' => ! $highlight])>
            <x-ui::icon :name="$icon" :size="20" />
        </span>
        <h3 @class(['min-w-0 flex-1 truncate text-sm font-medium', 'text-white' => $highlight, 'text-ink-secondary' => ! $highlight])>{{ $label }}</h3>
        {{ $menu ?? '' }}
    </header>
    <p class="text-[28px] font-semibold leading-9 tracking-tight" dir="ltr" @if ($countUp !== null) data-count-up="{{ $countUp }}" data-count-format="{{ $countFormat }}" @endif><bdi>{{ $value }}</bdi></p>
    <footer class="flex items-center gap-2">
        @if ($deltaText !== null)
            <span dir="ltr" @class([
                'inline-flex h-5 items-center gap-0.5 rounded-full px-1.5 text-xs font-medium',
                'bg-white/15 text-white' => $highlight,
                'bg-success-bg text-success' => ! $highlight && $trend === 'up',
                'bg-danger-bg text-danger' => ! $highlight && $trend === 'down',
                'bg-subtle text-ink-secondary' => ! $highlight && $trend === 'neutral',
            ])>
                <x-ui::icon :name="$trendIcon" :size="12" />{{ $deltaText }}
            </span>
        @endif
        @if ($comparison)
            <span @class(['min-w-0 flex-1 text-xs leading-4', 'text-white/80' => $highlight, 'text-ink-secondary' => ! $highlight])>{{ $comparison }}</span>
        @endif
        <svg class="ms-auto shrink-0" width="72" height="24" viewBox="0 0 72 24" fill="none" aria-hidden="true" focusable="false">
            <polyline points="{{ $points }}" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" @class(['text-white' => $highlight, 'text-success' => ! $highlight && $trend === 'up', 'text-danger' => ! $highlight && $trend === 'down', 'text-primary' => ! $highlight && $trend === 'neutral']) />
        </svg>
    </footer>
</article>
