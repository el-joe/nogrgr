{{-- Elora v1 round category (1:15436): photo (own image, else a product photo) + name. --}}
@props(['tile'])
<a href="{{ route('storefront.category', $tile['category']->slug) }}" {{ $attributes->class(['v1-circle']) }}>
    <span class="v1-circle-img">
        @if ($tile['image'])
            <img src="{{ $tile['image'] }}" alt="" width="81" height="81" loading="lazy" decoding="async">
        @else
            <span aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $tile['category']->name, 0, 1)) }}</span>
        @endif
    </span>
    <span class="v1-circle-name">{{ $tile['category']->name }}</span>
</a>
