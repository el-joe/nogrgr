{{-- Five rating stars (Ecommet: yellow #FFB00A, grey when off; tone="dark" on best-selling listings). --}}
@props(['rating' => 0, 'size' => 16, 'tone' => 'yellow', 'figma' => false])
@php($full = (int) round((float) $rating))
<span {{ $attributes->class(['eco-stars', 'eco-stars-dark' => $tone === 'dark']) }} role="img" aria-label="{{ __st('catalog.card.stars', ['rating' => number_format((float) $rating, 1)]) }}">
    @for ($i = 1; $i <= 5; $i++)
        @if ($figma)
            {!! \App\Shared\Support\Icon::svg('elo-star', (int) $size, 'class="ui-icon'.($i > $full ? ' is-off' : '').'"') !!}
        @else
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" @class(['is-off' => $i > $full]) aria-hidden="true"><path fill="currentColor" d="M12 2.5l2.94 6.08 6.56.95-4.75 4.63 1.12 6.54L12 17.6l-5.87 3.1 1.12-6.54L2.5 9.53l6.56-.95L12 2.5z"/></svg>
        @endif
    @endfor
</span>
