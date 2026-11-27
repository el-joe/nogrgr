{{--
    Elora section heading. style: start (title start, "see all" end — v2–v6), center (v1
    "Trending Now": big accent title + icon centred, see all end), split (v1 Best Seller /
    New Arrivals: see all start, accent title centred), center-plain (title centred, no
    link), center-light (white title centred on a coloured band). subtitle: optional line set in the
    store's page builder (VEN-032).
--}}
@props(['title', 'more' => null, 'style' => 'start', 'icon' => null, 'subtitle' => null])
@php($tone = in_array($style, ['center', 'split', 'center-plain'], true) ? 'text-elo-primary' : ($style === 'center-light' ? 'text-white' : 'text-[var(--elo-section-ink)]'))
<div {{ $attributes->class(['elo-heading', 'elo-heading-'.$style]) }} data-reveal="fade-up">
    @if ($style === 'split' && $more)
        <a href="{{ $more }}" class="elo-see-all elo-heading-link order-first"><x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />{{ __st('elora.home.see_all') }}</a>
    @endif
    <h2 @class(['elo-heading-title', $tone])>
        @if ($icon && $style !== 'start')<x-ui::icon :name="$icon" :size="$style === 'center' ? 40 : 26" />@endif
        <span>{{ $title }}</span>
    </h2>
    @if ($subtitle)
        <p class="elo-heading-subtitle w-full basis-full text-sm opacity-75 @if (in_array($style, ['center', 'split', 'center-plain', 'center-light'], true)) text-center @endif">{{ $subtitle }}</p>
    @endif
    @if ($style !== 'split' && $style !== 'center-plain' && $style !== 'center-light' && $more)
        <a href="{{ $more }}" class="elo-see-all elo-heading-link">{{ __st('elora.home.see_all') }}@if ($style === 'center')<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" />@endif</a>
    @endif
</div>
