{{-- Elora v1 product section: heading (1:15646 / 1:15727 / 1:15803) + clipped rail. --}}
@props(['cards' => [], 'section' => [], 'delivery' => null])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && $section['more'] && Route::has($section['more']) ? route($section['more']) : null;
        [$style, $icon] = match ($section['data'] ?? null) {
            'trending' => ['trending', 'trending'],
            'best' => ['split', 'award'],
            'newRail' => ['split', 'falling-star'],
            default => ['center', 'hand'],
        };
    @endphp
    <section {{ $attributes->class(['v1-section', 'v1-section-'.$style]) }} aria-label="{{ $title }}">
        <x-sf::elora.v1.heading :title="$title" :icon="$icon" :style="$style" :more="$more" :subtitle="$section['subtitle_text'] ?? null" />
        <x-sf::elora.v1.rail :cards="$cards" :delivery="$delivery" :label="$title" data-reveal="fade-up" />
    </section>
@endif
