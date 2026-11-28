{{-- Order line photo(s): one 86x81 tile, or the two stacked tiles of a multi-item order (1:18802). --}}
@props(['images' => []])
@php($images = array_values($images))
<span {{ $attributes->class(['eac-thumbs', 'eac-thumbs-stack' => count($images) > 1]) }}>
    @foreach (array_slice($images, 0, 2) as $image)
        <span class="eac-thumb">
            @if ($image)
                <img src="{{ $image }}" alt="" width="86" height="81" loading="lazy" decoding="async" class="size-full object-contain">
            @else
                <x-ui::icon name="image" :size="20" />
            @endif
        </span>
    @endforeach
    @if ($images === [])
        <span class="eac-thumb"><x-ui::icon name="image" :size="20" /></span>
    @endif
</span>
