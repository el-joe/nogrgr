{{--
    Icon button (Figma 25:10386). variant: ghost|secondary|primary · size: sm (32) | md (40).
    label is required: it becomes the aria-label and the tooltip (title).
--}}
@props(['icon', 'label', 'variant' => 'ghost', 'size' => 'md', 'href' => null, 'type' => 'button', 'iconSize' => null])
@php
    $classes = ['ui-btn', 'ui-btn-'.$variant, 'ui-btn-square', $size === 'sm' ? 'size-8' : 'size-10'];
    $glyph = $iconSize ?? ($size === 'sm' ? 16 : 20);
@endphp
@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-ui::icon :name="$icon" :size="$glyph" />{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-ui::icon :name="$icon" :size="$glyph" />{{ $slot }}
    </button>
@endif
