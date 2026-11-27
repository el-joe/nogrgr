{{--
    Souqify "Explore Products" (v1 1:73020): two rows of deal side cards in the accent tone
    (blue prices / countdown), swipeable. Hidden when empty.
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null;
    @endphp
    <section class="sq-section" aria-label="{{ $title }}">
        <div class="sq-hwrap">
            <x-sf::souqify.heading :title="$title" :subtitle="$section['subtitle_text'] ?? null" :more="$more" class="mb-6 max-lg:mb-3" />
            <x-sf::souqify.rail :cards="$cards" variant="side" tone="accent" :rows="count($cards) > 3 ? 2 : 1" :label="$title" />
        </div>
    </section>
@endif
