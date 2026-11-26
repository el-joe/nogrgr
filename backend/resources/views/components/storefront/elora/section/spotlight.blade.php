{{-- Elora "Best Seller" card-fan section (v2 / v6 center, v3 left stack) on the --elo-best-bg band. --}}
@props(['cards' => [], 'section' => []])
@if (count($cards) >= 3)
    @php($title = __st($section['title']))
    @php($more = isset($section['more']) && Route::has($section['more']) ? route($section['more']) : null)
    <section class="elo-section elo-band-best" aria-label="{{ $title }}">
        <div class="elo-home-wrap">
            <x-sf::elora.heading :title="$title" :more="$more" class="mb-4" />
            <x-sf::elora.spotlight :cards="$cards" :layout="$section['layout'] ?? 'center'" />
        </div>
    </section>
@elseif ($cards !== [])
    <x-sf::elora.section.rail :cards="$cards" :section="$section + ['heading' => 'start', 'band' => 'best']" />
@endif
