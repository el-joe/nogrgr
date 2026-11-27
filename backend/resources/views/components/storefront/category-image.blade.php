{{-- Round category thumbnail (AVIF / WebP / original) or an initial placeholder. --}}
@props(['category', 'size' => 82, 'eager' => false])
@php
    $src = $category->thumbnailUrl();
    $name = (string) $category->name;
@endphp
@if ($src)
    <picture>
        @if ($avif = $category->thumbnailAvifUrl())<source srcset="{{ $avif }}" type="image/avif">@endif
        @if ($webp = $category->thumbnailWebpUrl())<source srcset="{{ $webp }}" type="image/webp">@endif
        <img src="{{ $src }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" {{ $attributes }}>
    </picture>
@else
    <span class="eco-cat-ph grid place-items-center text-2xl font-medium" aria-hidden="true" {{ $attributes }}>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
@endif
