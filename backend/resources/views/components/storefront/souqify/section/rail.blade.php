{{--
    Souqify product rail section: heading + swipeable rail, on an optional band (--sq-new-bg /
    --sq-offers-bg). Hidden when empty. $section: title (static text key) or title_text, more
    (route name or null), band (new | offers | null).
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && $section['more'] && Route::has($section['more']) ? route($section['more']) : null;
        $band = $section['band'] ?? null;
    @endphp
    <section @class(['sq-section', 'sq-hband sq-hband-'.$band => $band]) aria-label="{{ $title }}">
        <div class="sq-hwrap">
            <x-sf::souqify.heading :title="$title" :more="$more" :tone="$band ? 'light' : 'dark'" class="mb-6 max-lg:mb-3" />
            <x-sf::souqify.rail :cards="$cards" :label="$title" />
        </div>
    </section>
@endif
