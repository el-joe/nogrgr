{{-- Product thumbnail on the #f5f5f5 tile (order lines 56px, returns 40px): size 40 | 48 | 56. --}}
@props(['src' => null, 'size' => 56])
@php($box = [40 => 'size-10', 48 => 'size-12', 56 => 'size-14'][(int) $size] ?? 'size-14')
<span {{ $attributes->class(['relative grid shrink-0 place-items-center overflow-hidden rounded-lg bg-[#f5f5f5]', $box]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="" width="{{ (int) $size }}" height="{{ (int) $size }}" loading="lazy" decoding="async" class="size-full object-contain bg-media">
    @else
        <x-ui::icon name="image" :size="20" class="text-sf-muted" />
    @endif
</span>
