{{-- 40px rounded thumbnail; brand gradient tile when there is no image (Figma placeholder). --}}
@props(['url' => null, 'webp' => null, 'avif' => null, 'size' => 'size-10'])
<span {{ $attributes->class([$size, 'inline-block shrink-0 overflow-hidden rounded-control bg-linear-to-br from-primary-subtle to-primary/40']) }} aria-hidden="true">
    @if ($url)
        <picture>
            @if ($avif)<source srcset="{{ $avif }}" type="image/avif">@endif
            @if ($webp)<source srcset="{{ $webp }}" type="image/webp">@endif
            <img src="{{ $url }}" alt="" class="size-full object-contain bg-media" loading="lazy">
        </picture>
    @endif
</span>
