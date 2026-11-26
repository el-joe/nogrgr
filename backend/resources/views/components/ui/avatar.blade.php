{{-- Avatar disc: image or initials on bg/inverse. size in px (default 36). --}}
@props(['name' => null, 'src' => null, 'size' => 36, 'tone' => 'inverse'])
@php
    $toneClass = $tone === 'brand' ? 'bg-primary text-on-primary' : 'bg-inverse text-on-inverse';
@endphp
<span {{ $attributes->class(['inline-grid shrink-0 place-items-center overflow-hidden rounded-full font-medium', $toneClass]) }} @style(['width: '.(int) $size.'px', 'height: '.(int) $size.'px', 'font-size: '.max(11, (int) round($size * 0.36)).'px']) @if ($name) title="{{ $name }}" @endif>
    @if ($src)
        <img src="{{ $src }}" alt="" class="size-full object-contain bg-media">
    @else
        <span aria-hidden="true">{{ \App\Shared\Support\Ui::initials($name) }}</span>
    @endif
    @if ($name)<span class="sr-only">{{ $name }}</span>@endif
</span>
