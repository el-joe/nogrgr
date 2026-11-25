{{--
    Badge / status pill (Figma 29:10291): 20h, pill, 12 Medium.
    tone: neutral|brand|success|warning|danger|info · variant: soft (tint + dot) | outline (surface + border + dot) | solid | count.
--}}
@props(['tone' => 'neutral', 'variant' => 'soft', 'dot' => null])
@php
    $tones = [
        'neutral' => ['bg-subtle text-ink-secondary', 'bg-ink-secondary', 'bg-ink-secondary text-surface'],
        'brand' => ['bg-primary-subtle text-ink-brand', 'bg-primary', 'bg-primary text-on-primary'],
        'success' => ['bg-success-bg text-success', 'bg-success', 'bg-success text-white'],
        'warning' => ['bg-warning-bg text-warning', 'bg-warning', 'bg-warning text-white'],
        'danger' => ['bg-danger-bg text-danger', 'bg-danger', 'bg-danger text-white'],
        'info' => ['bg-info-bg text-info', 'bg-info', 'bg-info text-white'],
    ];
    [$soft, $dotColor, $solid] = $tones[$tone] ?? $tones['neutral'];
    $showDot = $dot ?? in_array($variant, ['soft', 'outline'], true);
    $classes = match ($variant) {
        'outline' => 'border border-line bg-surface '.explode(' ', $soft)[1],
        'solid' => $solid,
        'count' => $solid.' min-w-5 justify-center px-1.5',
        default => $soft,
    };
@endphp
<span {{ $attributes->class(['inline-flex h-5 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full text-xs font-medium leading-4', 'px-2.5' => $variant !== 'count', $classes]) }}>
    @if ($showDot)
        <span class="size-1.5 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
