{{--
    Elora product rail section: heading (EloraVariants 'heading' style) + swipeable rail, on an
    optional band (--elo-new-bg / --elo-best-bg). Hidden when empty. $section: title (static
    text key) or title_text, heading, icon, more (route name or null), band.
--}}
@props(['cards' => [], 'section' => []])
@if ($cards !== [])
    @php
        $title = $section['title_text'] ?? __st($section['title']);
        $more = isset($section['more']) && $section['more'] && Route::has($section['more']) ? route($section['more']) : null;
        $band = $section['band'] ?? null;
    @endphp
    <section @class(['elo-section', 'elo-band-'.$band => $band]) aria-label="{{ $title }}">
        <div class="elo-home-wrap">
            <x-sf::elora.heading :title="$title" :subtitle="$section['subtitle_text'] ?? null" :more="$more" :style="$section['heading'] ?? 'start'" :icon="$section['icon'] ?? null" class="mb-6 max-lg:mb-4" />
            <x-sf::elora.rail :cards="$cards" :label="$title" />
        </div>
    </section>
@endif
