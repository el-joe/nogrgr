{{-- Pill button. variant: cta|accent|outline|danger|danger-outline · size: sm|md|lg · busy text shown while data-loading. --}}
@props(['variant' => 'cta', 'size' => 'md', 'type' => 'button', 'href' => null, 'icon' => null, 'busy' => null, 'block' => false])
@php($classes = ['sf-btn', 'sf-btn-'.$variant, 'sf-btn-'.$size => $size !== 'md', 'w-full' => $block])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<x-ui::icon :name="$icon" :size="18" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        <span class="sf-btn-idle inline-flex items-center gap-2">@if ($icon)<x-ui::icon :name="$icon" :size="18" />@endif{{ $slot }}</span>
        <span class="sf-btn-busy items-center gap-2"><x-ui::icon name="refresh" :size="16" class="mo-spin" />{{ $busy ?? __st('shell.saving') }}</span>
    </button>
@endif
