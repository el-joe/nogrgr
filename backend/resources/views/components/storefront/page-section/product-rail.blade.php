{{-- Store-page section "Product rail" (VEN-032): heading + swipeable product rail by the chosen source. --}}
@props(['section'])
<section class="grid gap-4" aria-labelledby="ps-{{ $section['key'] }}">
    <x-sf::page-section.heading :title="$section['title'] ?? __st('pages.sections.products')" :subtitle="$section['subtitle']" :id="'ps-'.$section['key']" />
    <x-sf::section.rail :cards="$section['cards']" :label="$section['title'] ?? __st('pages.sections.products')" />
</section>
