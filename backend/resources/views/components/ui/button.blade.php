{{--
    Button (Figma 25:10355). variant: primary|secondary|ghost|danger · size: sm|md|lg.
    Renders <a> when href is given. loading=true shows the spinner + loading text and
    blocks clicks (width kept); ajax forms toggle it automatically on the submit button.
    <x-ui::button icon="plus">{{ __('…') }}</x-ui::button>
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconTrailing' => null,
    'href' => null,
    'type' => 'button',
    'loading' => false,
    'loadingText' => null,
    'disabled' => false,
    'block' => false,
])
@php
    $sizes = [
        'sm' => ['h-8 px-3 gap-1.5 text-[13px] leading-4', 16],
        'md' => ['h-10 px-4 gap-2 text-sm leading-5', 18],
        'lg' => ['h-12 px-5 gap-2 text-base leading-6', 20],
    ];
    [$sizeClasses, $iconSize] = $sizes[$size] ?? $sizes['md'];
    $classes = ['ui-btn', 'ui-btn-'.$variant, $sizeClasses, 'w-full' => $block];
    $loadingLabel = $loadingText ?? __('ui.saving');
@endphp
@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }} @if ($loading) data-loading aria-busy="true" @endif>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @disabled($disabled) @if ($loading) data-loading aria-busy="true" @endif>
@endif
    <x-ui::spinner :size="$iconSize" />
    <span class="ui-btn-loading">{{ $loadingLabel }}</span>
    @if ($icon)
        <x-ui::icon :name="$icon" :size="$iconSize" class="ui-btn-icon" />
    @endif
    <span class="ui-btn-label">{{ $slot }}</span>
    @if ($iconTrailing)
        <x-ui::icon :name="$iconTrailing" :size="$iconSize" class="ui-btn-icon" />
    @endif
@if ($href && ! $disabled)
    </a>
@else
    </button>
@endif
