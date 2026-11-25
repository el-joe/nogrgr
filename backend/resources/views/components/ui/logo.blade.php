{{--
    Logo lockup (Figma 35:11335): mark + NOGRGR wordmark + surface tag (ADMIN / STORE / PARTNER).
    variant: dark (on the sidebar) | light (on surfaces) | hero (white mark on the brand gradient).
--}}
@props(['variant' => 'dark', 'tag' => null, 'compact' => false])
@php
    $onColor = in_array($variant, ['dark', 'hero'], true);
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }} dir="ltr">
    <span @class(['grid size-8 shrink-0 place-items-center rounded-[9px] text-lg font-semibold', 'bg-white text-primary' => $variant === 'hero', 'bg-primary text-white' => $variant !== 'hero'])>N</span>
    @unless ($compact)
        <span @class(['ui-sidebar-wordmark text-xl font-semibold tracking-[0.04em]', 'text-white' => $onColor, 'text-ink' => ! $onColor])>NOGRGR</span>
        @if ($tag)
            <span @class(['ui-sidebar-wordmark rounded-[5px] px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.06em]', 'bg-white/15 text-sidebar-ink' => $variant === 'dark', 'bg-white/20 text-white' => $variant === 'hero', 'bg-primary-subtle text-ink-brand' => $variant === 'light'])>{{ $tag }}</span>
        @endif
    @endunless
</span>
