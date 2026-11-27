{{--
    Souqify wide-card rows (v2 / v5 "New In" three rows on a band, v3 / v4 / v6 "Trending Now"
    two rows): a swipeable rail whose slides stack $rows wide cards. v4 shows the sold bar.
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null;
        $band = $section['band'] ?? null;
    @endphp
    <section @class(['sq-section', 'sq-hband sq-hband-'.$band => $band]) aria-label="{{ $title }}">
        <div class="sq-hwrap">
            <x-sf::souqify.heading :title="$title" :subtitle="$section['subtitle_text'] ?? null" :more="$more" :tone="$band ? 'light' : 'dark'" class="mb-6 max-lg:mb-3" />
            <x-sf::souqify.rail :cards="$cards" variant="wide" :rows="(int) ($section['rows'] ?? 2)" :progress="($section['progress'] ?? '') === '1'" :label="$title" />
        </div>
    </section>
@endif
