{{-- Skeleton placeholder (shimmer 1.2s; static with reduced motion). shape: line|circle|rect. --}}
@props(['shape' => 'line', 'width' => null, 'height' => null, 'lines' => 1])
@php
    $style = trim(($width ? 'width:'.$width.';' : '').($height ? 'height:'.$height.';' : ''));
@endphp
@if ($shape === 'line' && $lines > 1)
    <div {{ $attributes->class(['grid gap-2']) }} aria-hidden="true">
        @for ($line = 0; $line < $lines; $line++)
            <span class="ui-skeleton block h-3" @style(['width: '.($line === $lines - 1 ? '60%' : '100%')])></span>
        @endfor
    </div>
@else
    <span {{ $attributes->class(['ui-skeleton block', 'h-3' => $shape === 'line' && ! $height, 'rounded-full' => $shape === 'circle', 'rounded-control' => $shape === 'rect', 'size-10' => $shape === 'circle' && ! $width]) }} @if ($style) style="{{ $style }}" @endif aria-hidden="true"></span>
@endif
